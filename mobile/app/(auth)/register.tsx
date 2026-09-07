import React, { useState } from 'react';
import { View, Text, TextInput, Button, StyleSheet, ActivityIndicator } from 'react-native';
import { router } from 'expo-router';
import { register as registerService } from '../../src/services/auth';
import { saveToken } from '../../src/services/token';
import { useAuth } from '../../src/hooks/useAuth';

export default function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleRegister = async () => {
    setError(null);
    setLoading(true);
    
    const response = await registerService(name, email, password);
    
    if (response.success && response.data) {
      await saveToken(response.data.accessToken);
      router.replace('/'); 
    } else {
      setError(response.error?.message || 'Terjadi kesalahan saat pendaftaran');
    }
    
    setLoading(false);
  };

  return (
    <View style={styles.container}>
      {error && <Text style={styles.errorText}>{error}</Text>}
      
      <Text style={styles.label}>Nama</Text>
      <TextInput
        style={styles.input}
        value={name}
        onChangeText={setName}
        editable={!loading}
      />

      <Text style={styles.label}>Email</Text>
      <TextInput
        style={styles.input}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
        editable={!loading}
      />
      
      <Text style={styles.label}>Kata Sandi</Text>
      <TextInput
        style={styles.input}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        editable={!loading}
      />
      
      <View style={styles.buttonContainer}>
        {loading ? (
          <ActivityIndicator size="small" color="#0000ff" />
        ) : (
          <Button title="Daftar" onPress={handleRegister} disabled={!name || !email || !password} />
        )}
      </View>
      
      <Button 
        title="Sudah punya akun? Masuk" 
        onPress={() => router.back()} 
        disabled={loading}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    justifyContent: 'center',
  },
  label: {
    marginBottom: 5,
    fontSize: 16,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    padding: 10,
    marginBottom: 15,
    borderRadius: 5,
  },
  buttonContainer: {
    marginBottom: 15,
  },
  errorText: {
    color: 'red',
    marginBottom: 15,
    textAlign: 'center',
  },
});
