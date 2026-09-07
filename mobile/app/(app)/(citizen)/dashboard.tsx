import { View, Text, Button, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { useAuth } from '../../../src/hooks/useAuth';

export default function CitizenDashboard() {
  const { logout } = useAuth();
  const router = useRouter();

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Dashboard Warga</Text>
      <View style={styles.buttonContainer}>
        <Button title="Buat Laporan" onPress={() => router.push('/reports/create')} />
      </View>
      <View style={styles.buttonContainer}>
        <Button title="Laporan Saya" onPress={() => router.push('/reports')} />
      </View>
      <View style={styles.buttonContainer}>
        <Button title="Peta Publik" onPress={() => router.push('/map')} />
      </View>
      <View style={styles.buttonContainer}>
        <Button title="Keluar" onPress={logout} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 24,
    marginBottom: 20,
  },
  buttonContainer: {
    marginVertical: 10,
    width: '80%',
  },
});

