import { Redirect } from 'expo-router';
import { Platform, View, ActivityIndicator } from 'react-native';
import { useAuth } from '../src/hooks/useAuth';

const DESKTOP_UA = /Mobile|Android|iPhone|iPad|iPod/i;

export default function Index() {
  // Web platform: send desktop browsers to the Laravel Blade app.
  // Mobile browsers stay on Expo web; native apps are unaffected.
  if (Platform.OS === 'web') {
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent : '';
    if (!DESKTOP_UA.test(ua)) {
      const url = process.env.EXPO_PUBLIC_WEB_APP_URL;
      if (url) {
        window.location.href = url;
        return null;
      }
    }
  }

  const { token, user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center' }}>
        <ActivityIndicator size="large" color="#0000ff" />
      </View>
    );
  }

  if (!token || !user) {
    return <Redirect href="/(auth)/login" />;
  }

  if (user.role === 'citizen') {
    return <Redirect href="/(app)/(citizen)/dashboard" />;
  } else if (user.role === 'staff') {
    return <Redirect href="/(app)/(staff)/dashboard" />;
  } else if (user.role === 'crew') {
    return <Redirect href="/(app)/(crew)/dashboard" />;
  }

  return <Redirect href="/(auth)/login" />;
}
