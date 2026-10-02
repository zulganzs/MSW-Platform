/**
 * Lightweight HTTP router for Lambda.
 * Supports method + path pattern matching with :param extraction.
 */
export class Router {
  constructor() {
    this.routes = [];
  }

  /**
   * Register a route.
   * @param {string} method - HTTP method (GET, POST, PUT, PATCH, DELETE)
   * @param {string} path - Path pattern, e.g. "/api/reports/:id"
   * @param {Function} handler - Handler function
   */
  add(method, path, handler) {
    const parts = path.split('/').filter(Boolean);
    const paramNames = [];
    const regexParts = parts.map((part) => {
      if (part.startsWith(':')) {
        paramNames.push(part.slice(1));
        return '([^/]+)';
      }
      return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    });
    const regex = new RegExp(`^/${regexParts.join('/')}/?$`);
    this.routes.push({ method: method.toUpperCase(), regex, paramNames, handler });
  }

  /**
   * Match a method + path to a registered route.
   * @param {string} method
   * @param {string} path
   * @returns {{ handler: Function, params: Record<string, string> } | null}
   */
  match(method, path) {
    const upperMethod = method.toUpperCase();
    for (const route of this.routes) {
      if (route.method !== upperMethod) continue;
      const match = path.match(route.regex);
      if (match) {
        const params = {};
        route.paramNames.forEach((name, i) => {
          params[name] = decodeURIComponent(match[i + 1]);
        });
        return { handler: route.handler, params };
      }
    }
    return null;
  }
}
