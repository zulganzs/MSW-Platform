#!/usr/bin/env node

/**
 * setup-dynamodb.mjs
 *
 * Creates all 5 DynamoDB tables for the MSW Platform.
 * Uses Provisioned capacity to stay within the AWS Always Free tier:
 *   - Tables: 2 RCU / 2 WCU each
 *   - GSIs:   1 RCU / 1 WCU each
 *   - Total:  ~10 RCU / 10 WCU (well within 25/25 limit)
 *
 * Idempotent: skips tables that already exist.
 *
 * Usage: node setup-dynamodb.mjs
 */

import {
  DynamoDBClient,
  CreateTableCommand,
  DescribeTableCommand,
  waitUntilTableExists,
} from "@aws-sdk/client-dynamodb";

// ── Colour helpers ──────────────────────────────────────────────────────────
const C = {
  green: (s) => `\x1b[32m${s}\x1b[0m`,
  yellow: (s) => `\x1b[33m${s}\x1b[0m`,
  red: (s) => `\x1b[31m${s}\x1b[0m`,
  cyan: (s) => `\x1b[36m${s}\x1b[0m`,
  bold: (s) => `\x1b[1m${s}\x1b[0m`,
};

const REGION = process.env.AWS_REGION || "ap-southeast-1";
const PREFIX = process.env.TABLE_PREFIX || "msw";

const client = new DynamoDBClient({ region: REGION });

// ── Table definitions ───────────────────────────────────────────────────────
const TABLE_RCU = 2;
const TABLE_WCU = 2;
const GSI_RCU = 1;
const GSI_WCU = 1;

function provisionedThroughput(rcu, wcu) {
  return { ReadCapacityUnits: rcu, WriteCapacityUnits: wcu };
}

const tables = [
  // 1. msw-users
  {
    TableName: `${PREFIX}-users`,
    KeySchema: [{ AttributeName: "id", KeyType: "HASH" }],
    AttributeDefinitions: [
      { AttributeName: "id", AttributeType: "S" },
      { AttributeName: "email", AttributeType: "S" },
    ],
    ProvisionedThroughput: provisionedThroughput(TABLE_RCU, TABLE_WCU),
    GlobalSecondaryIndexes: [
      {
        IndexName: "email-index",
        KeySchema: [{ AttributeName: "email", KeyType: "HASH" }],
        Projection: { ProjectionType: "ALL" },
        ProvisionedThroughput: provisionedThroughput(GSI_RCU, GSI_WCU),
      },
    ],
  },

  // 2. msw-categories
  {
    TableName: `${PREFIX}-categories`,
    KeySchema: [{ AttributeName: "id", KeyType: "HASH" }],
    AttributeDefinitions: [
      { AttributeName: "id", AttributeType: "S" },
    ],
    ProvisionedThroughput: provisionedThroughput(TABLE_RCU, TABLE_WCU),
  },

  // 3. msw-reports
  {
    TableName: `${PREFIX}-reports`,
    KeySchema: [{ AttributeName: "id", KeyType: "HASH" }],
    AttributeDefinitions: [
      { AttributeName: "id", AttributeType: "S" },
      { AttributeName: "status", AttributeType: "S" },
      { AttributeName: "createdAt", AttributeType: "S" },
      { AttributeName: "userId", AttributeType: "S" },
    ],
    ProvisionedThroughput: provisionedThroughput(TABLE_RCU, TABLE_WCU),
    GlobalSecondaryIndexes: [
      {
        IndexName: "status-createdAt-index",
        KeySchema: [
          { AttributeName: "status", KeyType: "HASH" },
          { AttributeName: "createdAt", KeyType: "RANGE" },
        ],
        Projection: { ProjectionType: "ALL" },
        ProvisionedThroughput: provisionedThroughput(GSI_RCU, GSI_WCU),
      },
      {
        IndexName: "userId-index",
        KeySchema: [
          { AttributeName: "userId", KeyType: "HASH" },
          { AttributeName: "createdAt", KeyType: "RANGE" },
        ],
        Projection: { ProjectionType: "ALL" },
        ProvisionedThroughput: provisionedThroughput(GSI_RCU, GSI_WCU),
      },
    ],
  },

  // 4. msw-attachments
  {
    TableName: `${PREFIX}-attachments`,
    KeySchema: [{ AttributeName: "id", KeyType: "HASH" }],
    AttributeDefinitions: [
      { AttributeName: "id", AttributeType: "S" },
      { AttributeName: "reportId", AttributeType: "S" },
    ],
    ProvisionedThroughput: provisionedThroughput(TABLE_RCU, TABLE_WCU),
    GlobalSecondaryIndexes: [
      {
        IndexName: "reportId-index",
        KeySchema: [{ AttributeName: "reportId", KeyType: "HASH" }],
        Projection: { ProjectionType: "ALL" },
        ProvisionedThroughput: provisionedThroughput(GSI_RCU, GSI_WCU),
      },
    ],
  },

  // 5. msw-report-crew
  {
    TableName: `${PREFIX}-report-crew`,
    KeySchema: [
      { AttributeName: "reportId", KeyType: "HASH" },
      { AttributeName: "crewUserId", KeyType: "RANGE" },
    ],
    AttributeDefinitions: [
      { AttributeName: "reportId", AttributeType: "S" },
      { AttributeName: "crewUserId", AttributeType: "S" },
    ],
    ProvisionedThroughput: provisionedThroughput(TABLE_RCU, TABLE_WCU),
    GlobalSecondaryIndexes: [
      {
        IndexName: "crewUserId-index",
        KeySchema: [
          { AttributeName: "crewUserId", KeyType: "HASH" },
          { AttributeName: "reportId", KeyType: "RANGE" },
        ],
        Projection: { ProjectionType: "ALL" },
        ProvisionedThroughput: provisionedThroughput(GSI_RCU, GSI_WCU),
      },
    ],
  },
];

// ── Helpers ─────────────────────────────────────────────────────────────────

async function tableExists(tableName) {
  try {
    await client.send(new DescribeTableCommand({ TableName: tableName }));
    return true;
  } catch (err) {
    if (err.name === "ResourceNotFoundException") return false;
    throw err;
  }
}

async function createTable(definition) {
  const name = definition.TableName;

  if (await tableExists(name)) {
    console.log(C.yellow(`  ⏩  Table "${name}" already exists — skipping`));
    return;
  }

  console.log(C.cyan(`  ⏳  Creating table "${name}"...`));
  await client.send(new CreateTableCommand(definition));

  // Wait until the table is ACTIVE
  await waitUntilTableExists(
    { client, maxWaitTime: 120, minDelay: 2, maxDelay: 5 },
    { TableName: name },
  );

  const gsiCount = (definition.GlobalSecondaryIndexes || []).length;
  console.log(
    C.green(`  ✅  Table "${name}" is ACTIVE`) +
      (gsiCount > 0 ? ` (${gsiCount} GSI${gsiCount > 1 ? "s" : ""})` : ""),
  );
}

// ── Main ────────────────────────────────────────────────────────────────────

async function main() {
  console.log("");
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log(C.bold("  MSW Platform — DynamoDB Table Setup"));
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log(`  Region : ${C.cyan(REGION)}`);
  console.log(`  Prefix : ${C.cyan(PREFIX)}`);
  console.log("");

  // Print capacity budget
  let totalRCU = 0;
  let totalWCU = 0;
  for (const t of tables) {
    totalRCU += t.ProvisionedThroughput.ReadCapacityUnits;
    totalWCU += t.ProvisionedThroughput.WriteCapacityUnits;
    for (const gsi of t.GlobalSecondaryIndexes || []) {
      totalRCU += gsi.ProvisionedThroughput.ReadCapacityUnits;
      totalWCU += gsi.ProvisionedThroughput.WriteCapacityUnits;
    }
  }
  console.log(
    `  Capacity budget: ${C.cyan(`${totalRCU} RCU / ${totalWCU} WCU`)} (limit: 25/25)`,
  );
  console.log("");

  if (totalRCU > 25 || totalWCU > 25) {
    console.error(C.red("  ❌  Capacity budget exceeds free-tier limits!"));
    process.exit(1);
  }

  for (const table of tables) {
    await createTable(table);
  }

  console.log("");
  console.log(C.green(C.bold("  ✅  All DynamoDB tables ready!")));
  console.log("");
}

main().catch((err) => {
  console.error(C.red(`\n  ❌  Fatal error: ${err.message}\n`));
  process.exit(1);
});
