import { Redirect } from 'expo-router';
import { View, ActivityIndicator } from 'react-native';
import { useAuth } from '../src/hooks/useAuth';

export default function Index() {
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
