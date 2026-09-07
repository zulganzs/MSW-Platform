import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, RefreshControl, Button } from 'react-native';
import { useRouter } from 'expo-router';
import { api } from '../../../src/services/api';
import { StatusBadge, ReportStatus } from '../../../src/components/StatusBadge';
import { useAuth } from '../../../src/hooks/useAuth';

interface Report {
  id: number;
  description: string;
  status: ReportStatus;
  created_at: string;
  category: {
    name: string;
  };
}

interface ApiResponse {
  data: Report[];
  next_page_url: string | null;
}

export default function CrewDashboard() {
  const router = useRouter();
  const { logout } = useAuth();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchReports = useCallback(async () => {
    try {
      setError(null);
      const response = await api.get<ApiResponse>('/crew/reports');
      setReports(response.data.data);
    } catch (err) {
      setError('Gagal memuat laporan');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchReports();
  }, [fetchReports]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchReports();
  }, [fetchReports]);

  const renderItem = ({ item }: { item: Report }) => (
    <TouchableOpacity
      testID="report-card"
      style={styles.card}
      onPress={() => router.push(`/(crew)/reports/${item.id}`)}
    >
      <Text style={styles.description} numberOfLines={2}>{item.description}</Text>
      <Text style={styles.category}>{item.category.name}</Text>
      <Text style={styles.date}>{new Date(item.created_at).toLocaleDateString('id-ID')}</Text>
      <StatusBadge status={item.status} />
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Dashboard Petugas</Text>
        <Button title="Keluar" onPress={logout} />
      </View>
      {error ? (
        <View style={styles.center}>
          <Text style={styles.errorText}>{error}</Text>
          <Button title="Coba Lagi" onPress={onRefresh} />
        </View>
      ) : (
        <FlatList
          data={reports}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderItem}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>Belum ada laporan ditugaskan</Text>
          }
          contentContainerStyle={styles.listContainer}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f3f4f6',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#111827',
  },
  listContainer: {
    padding: 16,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  card: {
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 8,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  description: {
    fontSize: 16,
    fontWeight: '500',
    marginBottom: 4,
  },
  category: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 4,
  },
  date: {
    fontSize: 12,
    color: '#9ca3af',
    marginBottom: 8,
  },
  emptyText: {
    textAlign: 'center',
    marginTop: 32,
    color: '#6b7280',
  },
  errorText: {
    color: '#ef4444',
    marginBottom: 16,
  },
});
