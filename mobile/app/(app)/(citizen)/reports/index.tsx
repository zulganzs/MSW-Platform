import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, RefreshControl, Button } from 'react-native';
import { useRouter } from 'expo-router';
import { api } from '../../../../src/services/api';
import { StatusBadge, ReportStatus } from '../../../../src/components/StatusBadge';

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

export default function CitizenReportsList() {
  const router = useRouter();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [nextPageUrl, setNextPageUrl] = useState<string | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);

  const fetchReports = async (url: string = '/reports', isRefresh: boolean = false) => {
    try {
      if (!isRefresh && !loadingMore) setLoading(true);
      setError(null);
      const response = await api.get<ApiResponse>(url);
      
      const newReports = response.data.data;
      setReports(isRefresh ? newReports : [...reports, ...newReports]);
      setNextPageUrl(response.data.next_page_url);
    } catch (err) {
      setError('Gagal memuat laporan');
    } finally {
      setLoading(false);
      setRefreshing(false);
      setLoadingMore(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchReports('/reports', true);
  }, []);

  const loadMore = () => {
    if (nextPageUrl && !loadingMore) {
      setLoadingMore(true);
      
      const baseUrlStr = process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000/api';
      fetchReports(nextPageUrl.replace(baseUrlStr, ''));
    }
  };

  const renderItem = ({ item }: { item: Report }) => (
    <TouchableOpacity
      testID="report-card"
      style={styles.card}
      onPress={() => router.push(`/reports/${item.id}`)}
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

  if (error) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>{error}</Text>
        <Button title="Segarkan" onPress={onRefresh} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={reports}
        keyExtractor={(item) => item.id.toString()}
        renderItem={renderItem}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <Text style={styles.emptyText}>Belum ada laporan</Text>
        }
        ListFooterComponent={
          nextPageUrl ? (
            <View style={styles.footer}>
              {loadingMore ? <ActivityIndicator /> : <Button title="Muat Lebih Banyak" onPress={loadMore} />}
            </View>
          ) : null
        }
        contentContainerStyle={styles.listContainer}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f3f4f6',
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
  footer: {
    paddingVertical: 16,
  },
});
