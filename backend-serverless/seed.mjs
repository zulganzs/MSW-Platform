#!/usr/bin/env node

/**
 * seed.mjs
 *
 * Seeds the MSW Platform DynamoDB tables with:
 *   - 10 waste categories (Indonesian)
 *   - 3 test users (citizen, staff, crew) with bcrypt-hashed passwords
 *
 * Idempotent: checks for existing records before inserting.
 *
 * Usage: node seed.mjs
 */

import { DynamoDBClient } from "@aws-sdk/client-dynamodb";
import {
  DynamoDBDocumentClient,
  PutCommand,
  ScanCommand,
  QueryCommand,
} from "@aws-sdk/lib-dynamodb";
import bcrypt from "bcryptjs";
import crypto from "node:crypto";

// ── Colour helpers ──────────────────────────────────────────────────────────
const C = {
  green: (s) => `\x1b[32m${s}\x1b[0m`,
  yellow: (s) => `\x1b[33m${s}\x1b[0m`,
  red: (s) => `\x1b[31m${s}\x1b[0m`,
  cyan: (s) => `\x1b[36m${s}\x1b[0m`,
  bold: (s) => `\x1b[1m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
};

const REGION = process.env.AWS_REGION || "ap-southeast-1";
const PREFIX = process.env.TABLE_PREFIX || "msw";

const rawClient = new DynamoDBClient({ region: REGION });
const ddb = DynamoDBDocumentClient.from(rawClient, {
  marshallOptions: { removeUndefinedValues: true },
});

const TABLES = {
  users: `${PREFIX}-users`,
  categories: `${PREFIX}-categories`,
};

// ── Category data ───────────────────────────────────────────────────────────

const CATEGORIES = [
  { name: "Tumpukan Sampah Liar", description: "Penumpukan sampah ilegal di lokasi yang bukan tempat pembuangan resmi" },
  { name: "Tempat Sampah Penuh", description: "Tempat sampah atau bak sampah yang sudah melebihi kapasitas" },
  { name: "Sampah Tidak Terangkut", description: "Sampah yang seharusnya sudah diangkut tetapi masih tertinggal" },
  { name: "Limbah B3 Ilegal", description: "Pembuangan limbah Bahan Berbahaya dan Beracun secara ilegal" },
  { name: "Saluran Air Tersumbat", description: "Saluran air atau drainase yang tersumbat oleh sampah" },
  { name: "Sampah di Fasilitas Umum", description: "Sampah berserakan di taman, trotoar, halte, atau fasilitas umum lainnya" },
  { name: "Pembakaran Sampah", description: "Aktivitas pembakaran sampah terbuka yang mencemari udara" },
  { name: "Kontainer Rusak", description: "Kontainer atau tempat sampah besar yang rusak dan perlu perbaikan" },
  { name: "Sampah Medis", description: "Sampah medis (masker, jarum, perban) yang dibuang sembarangan" },
  { name: "Lainnya", description: "Kategori lain yang tidak termasuk dalam daftar di atas" },
];

// ── Test user data ──────────────────────────────────────────────────────────

const TEST_PASSWORD = "Password123!";
const BCRYPT_ROUNDS = 10;

const USERS = [
  {
    name: "Budi Santoso",
    email: "citizen@test.com",
    role: "citizen",
  },
  {
    name: "Siti Rahayu",
    email: "staff@test.com",
    role: "staff",
  },
  {
    name: "Joko Prasetyo",
    email: "crew@test.com",
    role: "crew",
  },
];

// ── Helpers ─────────────────────────────────────────────────────────────────

function uuid() {
  return crypto.randomUUID();
}

function isoNow() {
  return new Date().toISOString();
}

/**
 * Check if a user with the given email already exists via the email-index GSI.
 */
async function userExistsByEmail(email) {
  const result = await ddb.send(
    new QueryCommand({
      TableName: TABLES.users,
      IndexName: "email-index",
      KeyConditionExpression: "email = :email",
      ExpressionAttributeValues: { ":email": email },
      Limit: 1,
    }),
  );
  return (result.Items || []).length > 0;
}

/**
 * Check if a category with the given name already exists (scan-based, small table).
 */
async function categoryExistsByName(name) {
  const result = await ddb.send(
    new ScanCommand({
      TableName: TABLES.categories,
      FilterExpression: "#n = :name",
      ExpressionAttributeNames: { "#n": "name" },
      ExpressionAttributeValues: { ":name": name },
      Limit: 1,
    }),
  );
  return (result.Items || []).length > 0;
}

// ── Seed categories ─────────────────────────────────────────────────────────

async function seedCategories() {
  console.log(C.bold("\n  📂 Seeding Categories"));
  console.log(C.dim("  ─────────────────────────────────────"));

  let created = 0;
  let skipped = 0;

  for (const cat of CATEGORIES) {
    if (await categoryExistsByName(cat.name)) {
      console.log(C.yellow(`    ⏩  "${cat.name}" — exists`));
      skipped++;
      continue;
    }

    const now = isoNow();
    await ddb.send(
      new PutCommand({
        TableName: TABLES.categories,
        Item: {
          id: uuid(),
          name: cat.name,
          description: cat.description,
          createdAt: now,
          updatedAt: now,
        },
      }),
    );
    console.log(C.green(`    ✅  "${cat.name}" — created`));
    created++;
  }

  console.log(
    `\n    ${C.green(`${created} created`)}, ${C.yellow(`${skipped} skipped`)}`,
  );
}

// ── Seed users ──────────────────────────────────────────────────────────────

async function seedUsers() {
  console.log(C.bold("\n  👥 Seeding Test Users"));
  console.log(C.dim("  ─────────────────────────────────────"));

  // Hash the shared password once
  console.log(C.dim("    Hashing password..."));
  const passwordHash = await bcrypt.hash(TEST_PASSWORD, BCRYPT_ROUNDS);

  let created = 0;
  let skipped = 0;

  for (const user of USERS) {
    if (await userExistsByEmail(user.email)) {
      console.log(C.yellow(`    ⏩  ${user.email} (${user.role}) — exists`));
      skipped++;
      continue;
    }

    const now = isoNow();
    await ddb.send(
      new PutCommand({
        TableName: TABLES.users,
        Item: {
          id: uuid(),
          name: user.name,
          email: user.email,
          password: passwordHash,
          role: user.role,
          createdAt: now,
          updatedAt: now,
        },
      }),
    );
    console.log(C.green(`    ✅  ${user.email} (${user.role}) — created`));
    created++;
  }

  console.log(
    `\n    ${C.green(`${created} created`)}, ${C.yellow(`${skipped} skipped`)}`,
  );
}

// ── Main ────────────────────────────────────────────────────────────────────

async function main() {
  console.log("");
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log(C.bold("  MSW Platform — Database Seeder"));
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log(`  Region : ${C.cyan(REGION)}`);
  console.log(`  Prefix : ${C.cyan(PREFIX)}`);

  await seedCategories();
  await seedUsers();

  console.log("");
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log(C.green(C.bold("  ✅  Seeding complete!")));
  console.log(C.bold("═══════════════════════════════════════════════"));
  console.log("");
  console.log(C.bold("  Test credentials:"));
  console.log(`    Email    : ${C.cyan("citizen@test.com")} / ${C.cyan("staff@test.com")} / ${C.cyan("crew@test.com")}`);
  console.log(`    Password : ${C.cyan(TEST_PASSWORD)}`);
  console.log("");
}

main().catch((err) => {
  console.error(C.red(`\n  ❌  Fatal error: ${err.message}\n`));
  console.error(err);
  process.exit(1);
});
