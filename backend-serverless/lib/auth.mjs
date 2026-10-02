import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';

const JWT_SECRET = process.env.JWT_SECRET || 'change-me-in-production';
const JWT_EXPIRY_SECONDS = 30 * 24 * 60 * 60; // 30 days

/**
 * Hash a plaintext password with bcrypt.
 * @param {string} password
 * @returns {Promise<string>}
 */
export async function hashPassword(password) {
  return bcrypt.hash(password, 10);
}

/**
 * Verify a plaintext password against a bcrypt hash.
 * @param {string} password
 * @param {string} hash
 * @returns {Promise<boolean>}
 */
export async function verifyPassword(password, hash) {
  return bcrypt.compare(password, hash);
}

/**
 * Create a signed JWT token.
 * @param {{ sub: string, email: string, role: string }} payload
 * @returns {string}
 */
export function createToken(payload) {
  return jwt.sign(payload, JWT_SECRET, { expiresIn: JWT_EXPIRY_SECONDS });
}

/**
 * Verify and decode a JWT token.
 * @param {string} token
 * @returns {object|null} decoded payload or null if invalid
 */
export function verifyToken(token) {
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch {
    return null;
  }
}

/**
 * Extract user info from the Authorization header of a Lambda event.
 * @param {object} event - Lambda Function URL event
 * @returns {{ id: string, email: string, role: string }|null}
 */
export function extractUser(event) {
  const authHeader =
    event.headers?.authorization || event.headers?.Authorization || '';
  if (!authHeader.startsWith('Bearer ')) return null;
  const token = authHeader.slice(7);
  const decoded = verifyToken(token);
  if (!decoded) return null;
  return { id: decoded.sub, email: decoded.email, role: decoded.role };
}
