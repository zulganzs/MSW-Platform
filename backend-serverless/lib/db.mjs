import {
  DynamoDBClient,
  PutItemCommand,
  GetItemCommand,
  DeleteItemCommand,
  UpdateItemCommand,
  QueryCommand,
  ScanCommand,
  BatchGetItemCommand,
} from '@aws-sdk/client-dynamodb';
import { marshall, unmarshall } from '@aws-sdk/util-dynamodb';

const client = new DynamoDBClient({ region: process.env.AWS_REGION || 'ap-southeast-1' });
const TABLE_PREFIX = process.env.TABLE_PREFIX || 'msw';

/**
 * Get a fully-qualified DynamoDB table name.
 * @param {string} name - Short name, e.g. "users"
 * @returns {string} e.g. "msw-users"
 */
export function getTableName(name) {
  return `${TABLE_PREFIX}-${name}`;
}

/**
 * Put (create/overwrite) an item.
 * @param {string} table - Short table name
 * @param {object} item - Plain JS object
 */
export async function putItem(table, item) {
  await client.send(
    new PutItemCommand({
      TableName: getTableName(table),
      Item: marshall(item, { removeUndefinedValues: true }),
    })
  );
}

/**
 * Get a single item by primary key.
 * @param {string} table
 * @param {object} key - e.g. { id: "abc" }
 * @returns {object|null}
 */
export async function getItem(table, key) {
  const result = await client.send(
    new GetItemCommand({
      TableName: getTableName(table),
      Key: marshall(key),
    })
  );
  return result.Item ? unmarshall(result.Item) : null;
}

/**
 * Query using a GSI or primary key.
 * @param {string} table
 * @param {string|null} indexName - GSI name, or null for table PK
 * @param {object} keyCondition - { expression, values, names }
 * @param {object} [options]
 * @param {boolean} [options.scanForward=true]
 * @param {number} [options.limit]
 * @param {object} [options.filter] - { expression, values, names }
 * @param {object} [options.startKey]
 * @returns {{ items: object[], lastKey: object|undefined }}
 */
export async function queryIndex(table, indexName, keyCondition, options = {}) {
  const params = {
    TableName: getTableName(table),
    KeyConditionExpression: keyCondition.expression,
    ExpressionAttributeValues: marshall(keyCondition.values),
    ScanIndexForward: options.scanForward !== false,
  };
  if (indexName) params.IndexName = indexName;
  if (keyCondition.names) params.ExpressionAttributeNames = keyCondition.names;
  if (options.limit) params.Limit = options.limit;
  if (options.startKey) params.ExclusiveStartKey = marshall(options.startKey);
  if (options.filter) {
    params.FilterExpression = options.filter.expression;
    params.ExpressionAttributeValues = marshall({
      ...keyCondition.values,
      ...options.filter.values,
    });
    if (options.filter.names) {
      params.ExpressionAttributeNames = {
        ...(params.ExpressionAttributeNames || {}),
        ...options.filter.names,
      };
    }
  }

  const result = await client.send(new QueryCommand(params));
  return {
    items: (result.Items || []).map((i) => unmarshall(i)),
    lastKey: result.LastEvaluatedKey ? unmarshall(result.LastEvaluatedKey) : undefined,
  };
}

/**
 * Query ALL items (auto-paginate through LastEvaluatedKey).
 * @param {string} table
 * @param {string|null} indexName
 * @param {object} keyCondition
 * @returns {object[]}
 */
export async function queryAll(table, indexName, keyCondition) {
  const allItems = [];
  let lastKey;
  do {
    const result = await queryIndex(table, indexName, keyCondition, { startKey: lastKey });
    allItems.push(...result.items);
    lastKey = result.lastKey;
  } while (lastKey);
  return allItems;
}

/**
 * Scan an entire table with optional filters.
 * @param {string} table
 * @param {object|null} filterExpression - { expression, values, names }
 * @param {object} [options]
 * @param {number} [options.limit]
 * @param {object} [options.startKey]
 * @returns {{ items: object[], lastKey: object|undefined }}
 */
export async function scanTable(table, filterExpression, options = {}) {
  const params = {
    TableName: getTableName(table),
  };
  if (filterExpression) {
    params.FilterExpression = filterExpression.expression;
    if (filterExpression.values) {
      params.ExpressionAttributeValues = marshall(filterExpression.values);
    }
    if (filterExpression.names) {
      params.ExpressionAttributeNames = filterExpression.names;
    }
  }
  if (options.limit) params.Limit = options.limit;
  if (options.startKey) params.ExclusiveStartKey = marshall(options.startKey);

  const result = await client.send(new ScanCommand(params));
  return {
    items: (result.Items || []).map((i) => unmarshall(i)),
    lastKey: result.LastEvaluatedKey ? unmarshall(result.LastEvaluatedKey) : undefined,
  };
}

/**
 * Scan ALL items in a table (auto-paginate).
 * @param {string} table
 * @param {object|null} filterExpression
 * @returns {object[]}
 */
export async function scanAll(table, filterExpression) {
  const allItems = [];
  let lastKey;
  do {
    const result = await scanTable(table, filterExpression, { startKey: lastKey });
    allItems.push(...result.items);
    lastKey = result.lastKey;
  } while (lastKey);
  return allItems;
}

/**
 * Update specific attributes of an item.
 * @param {string} table
 * @param {object} key
 * @param {object} updates - plain object of fields to set
 */
export async function updateItem(table, key, updates) {
  const entries = Object.entries(updates);
  if (entries.length === 0) return;

  const expressionParts = [];
  const attrValues = {};
  const attrNames = {};

  entries.forEach(([k, v], i) => {
    const nameKey = `#f${i}`;
    const valKey = `:v${i}`;
    expressionParts.push(`${nameKey} = ${valKey}`);
    attrNames[nameKey] = k;
    attrValues[valKey] = v;
  });

  await client.send(
    new UpdateItemCommand({
      TableName: getTableName(table),
      Key: marshall(key),
      UpdateExpression: `SET ${expressionParts.join(', ')}`,
      ExpressionAttributeNames: attrNames,
      ExpressionAttributeValues: marshall(attrValues, { removeUndefinedValues: true }),
    })
  );
}

/**
 * Delete an item by key.
 * @param {string} table
 * @param {object} key
 */
export async function deleteItem(table, key) {
  await client.send(
    new DeleteItemCommand({
      TableName: getTableName(table),
      Key: marshall(key),
    })
  );
}

/**
 * Batch get items from a single table.
 * @param {string} table
 * @param {object[]} keys - array of key objects, e.g. [{ id: "abc" }, ...]
 * @returns {object[]}
 */
export async function batchGetItems(table, keys) {
  if (keys.length === 0) return [];
  const tableName = getTableName(table);
  const allItems = [];

  // DynamoDB limits batch get to 100 items at a time
  for (let i = 0; i < keys.length; i += 100) {
    const batch = keys.slice(i, i + 100);
    const result = await client.send(
      new BatchGetItemCommand({
        RequestItems: {
          [tableName]: {
            Keys: batch.map((k) => marshall(k)),
          },
        },
      })
    );
    const items = result.Responses?.[tableName] || [];
    allItems.push(...items.map((item) => unmarshall(item)));
  }
  return allItems;
}
