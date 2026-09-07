import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { User, login as loginService, logout as logoutService, AuthResponse } from '../services/auth';
import { getToken, saveToken, clearToken } from '../services/token';
import { api } from '../services/api';

interface AuthContextType {
  token: string | null;
  user: User | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<AuthResponse>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadAuth() {
      try {
        const storedToken = await getToken();
        if (storedToken) {
          setToken(storedToken);
          const response = await api.get('/user');
          setUser(response.data);
        }
      } catch (error) {
        await clearToken();
        setToken(null);
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    }
    loadAuth();
  }, []);

  const login = async (email: string, password: string): Promise<AuthResponse> => {
    const response = await loginService(email, password);
    if (response.success && response.data) {
      await saveToken(response.data.accessToken);
      setToken(response.data.accessToken);
      setUser(response.data.user);
    }
    return response;
  };

  const logout = async () => {
    await logoutService();
    await clearToken();
    setToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ token, user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}