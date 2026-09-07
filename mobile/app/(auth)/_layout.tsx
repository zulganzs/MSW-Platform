import { Stack } from 'expo-router';

export default function AuthLayout() {
  return (
    <Stack>
      <Stack.Screen name="login" options={{ title: 'Masuk' }} />
      <Stack.Screen name="register" options={{ title: 'Daftar' }} />
    </Stack>
  );
}
