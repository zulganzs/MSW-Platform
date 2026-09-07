import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Image, ScrollView, Button } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { api } from '../../../../src/services/api';
import { StatusBadge, ReportStatus } from '../../../../src/components/StatusBadge';

// ponytail: backend stores relative file_path; needs absolute URL until API emits full URLs (S3/CDN upgrade path)
const WEB_APP_URL = process.env.EXPO_PUBLIC_WEB_APP_URL ?? '';

function toImageUrl(file_path: string): string {
  if (file_path.startsWith('http')) return file_path;
  return `${WEB_APP_URL}/storage/${file_path}`;
}

interface Attachment {
  id: number;
  file_path: string;
  type: string;
}

interface ReportDetail {
  id: number;
  description: string;
  status: ReportStatus;
  created_at: string;
  latitude: number;
  longitude: number;
  category: {
    name: string;
  };
  attachments?: Attachment[];
  crews?: {
    name: string;
  }[];
}

export default function CitizenReportDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [report, setReport] = useState<ReportDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    const fetchDetail = async () => {
      try {
        setLoading(true);
        setError(null);
        setNotFound(false);
        const response = await api.get<{ data: ReportDetail }>(`/reports/${id}`);
        setReport(response.data.data || response.data as unknown as ReportDetail); // handle both {data:...} and direct object depending on API shape
      } catch (err: any) {
        if (err.error?.code === 404 || err.error?.code === 403) {
          setNotFound(true);
        } else {
          setError('Gagal memuat laporan');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchDetail();
  }, [id]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (notFound) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>Tidak Ditemukan</Text>
        <Button title="Kembali" onPress={() => router.back()} />
      </View>
    );
  }

  if (error || !report) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>{error || 'Gagal memuat laporan'}</Text>
        <Button title="Kembali" onPress={() => router.back()} />
      </View>
    );
  }

  const submissionPhoto = report.attachments?.find(a => a.type === 'submission');
  const closurePhoto = report.attachments?.find(a => a.type === 'closure');
  const showCrew = report.crews?.length && ['assigned', 'in_progress', 'completed'].includes(report.status);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <StatusBadge status={report.status} />
      
      <View style={styles.section}>
        <Text style={styles.label}>Kategori</Text>
        <Text style={styles.value}>{report.category.name}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.label}>Deskripsi</Text>
        <Text style={styles.value}>{report.description}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.label}>Tanggal</Text>
        <Text style={styles.value}>{new Date(report.created_at).toLocaleString('id-ID')}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.label}>Lokasi</Text>
        <Text style={styles.value}>{report.latitude}, {report.longitude}</Text>
      </View>

      {showCrew && (
        <View style={styles.section}>
          <Text style={styles.label}>Petugas</Text>
          <Text style={styles.value}>{report.crews![0].name}</Text>
        </View>
      )}

      {submissionPhoto && (
        <View style={styles.section}>
          <Text style={styles.label}>Foto Laporan</Text>
          <Image 
            source={{ uri: toImageUrl(submissionPhoto.file_path) }}
            style={styles.image} 
            resizeMode="cover"
          />
        </View>
      )}

      {report.status === 'completed' && closurePhoto && (
        <View style={styles.section}>
          <Text style={styles.label}>Foto Penyelesaian</Text>
          <Image 
            source={{ uri: toImageUrl(closurePhoto.file_path) }}
            style={styles.image} 
            resizeMode="cover"
          />
        </View>
      )}
      
      <View style={styles.buttonContainer}>
         <Button title="Kembali" onPress={() => router.back()} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    padding: 16,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  section: {
    marginTop: 16,
  },
  label: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 4,
  },
  value: {
    fontSize: 16,
    color: '#111827',
  },
  image: {
    width: '100%',
    height: 200,
    borderRadius: 8,
    marginTop: 8,
    backgroundColor: '#f3f4f6',
  },
  errorText: {
    color: '#ef4444',
    marginBottom: 16,
    fontSize: 16,
  },
  buttonContainer: {
    marginTop: 24,
  }
});
