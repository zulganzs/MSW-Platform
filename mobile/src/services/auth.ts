import { api } from './api';

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface AuthResponse {
  success: boolean;
  data?: {
    accessToken: string;
    user: User;
  };
  error?: {
    code: number;
    message: string;
  };
}

export async function login(email: string, password: string, deviceName: string = 'mobile'): Promise<AuthResponse> {
  try {
    const response = await api.post('/login', { email, password, device_name: deviceName });
    return {
      success: true,
      data: {
        accessToken: response.data.token,
        user: response.data.user,
      },
    };
  } catch (error: any) {
    return error; // Already normalised by interceptor
  }
}

export async function register(name: string, email: string, password: string, deviceName: string = 'mobile'): Promise<AuthResponse> {
  try {
    const response = await api.post('/register', { name, email, password, device_name: deviceName });
    return {
      success: true,
      data: {
        accessToken: response.data.token,
        user: response.data.user,
      },
    };
  } catch (error: any) {
    return error; // Already normalised by interceptor
  }
}

export async function logout(): Promise<{ success: boolean; error?: { code: number; message: string } }> {
  try {
    await api.post('/logout');
    return { success: true };
  } catch (error: any) {
    return error; // Already normalised by interceptor
  }
}
