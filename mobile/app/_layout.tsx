import { Stack } from 'expo-router';
import Head from 'expo-router/head';
import { AuthProvider } from '../src/hooks/useAuth';
import { PwaInstallPrompt } from '../src/components/PwaInstallPrompt';

export default function RootLayout() {
  return (
    <AuthProvider>
      <Head>
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#3b82f6" />
      </Head>
      <PwaInstallPrompt />
      <Stack>
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />
        <Stack.Screen name="(app)" options={{ headerShown: false }} />
      </Stack>
    </AuthProvider>
  );
}
