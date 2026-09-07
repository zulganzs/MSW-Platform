import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, ScrollView, Button, Alert, Image, Platform } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { api } from '../../../../src/services/api';
import { StatusBadge, ReportStatus } from '../../../../src/components/StatusBadge';

interface ReportDetail {
  id: number;
  description: string;
  status: ReportStatus;
  created_at: string;
  latitude: number;
  longitude: number;
  visibility: string;
  category: { name: string };
  user?: { name: string } | null;
}

export default function CrewReportDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [report, setReport] = useState<ReportDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [photoBlob, setPhotoBlob] = useState<Blob | null>(null);
  const [photoExt, setPhotoExt] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    const fetchDetail = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await api.get<ReportDetail>(`/reports/${id}`);
        // Public show returns the report object directly (not wrapped in {data:...})
        setReport(response.data);
      } catch (err: any) {
        if (err?.error?.code === 404 || err?.error?.code === 403) {
          setError('Laporan tidak ditemukan');
        } else {
          setError('Gagal memuat laporan');
        }
      } finally {
        setLoading(false);
      }
    };

    fetchDetail();
  }, [id]);

  const handleStart = async () => {
    try {
      setSubmitting(true);
      await api.patch(`/crew/reports/${id}/status`, { status: 'in_progress' });
      // Update local status only; the PATCH response's report.fresh() lacks
      // eager-loaded relationships, so we keep the existing category/user.
      setReport((prev) => (prev ? { ...prev, status: 'in_progress' } : prev));
    } catch (err: any) {
      Alert.alert('Gagal', err?.error?.message || 'Gagal memperbarui status');
    } finally {
      setSubmitting(false);
    }
  };

  const handleComplete = async () => {
    try {
      let result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 1,
        allowsEditing: false, // simpler for web compatibility
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const asset = result.assets[0];
        setPhotoUri(asset.uri);
        
        let localPhotoBlob = null;
        let localPhotoExt = null;

        // On web, we need to convert the data URL or blob URL to a real Blob for upload
        if (Platform.OS === 'web') {
          try {
            const response = await fetch(asset.uri);
            const blob = await response.blob();
            localPhotoBlob = blob;
            setPhotoBlob(blob);
            
            // Extract extension from mime type
            const mimeType = asset.mimeType || blob.type;
            let ext = 'jpg';
            if (mimeType === 'image/png') ext = 'png';
            if (mimeType === 'image/webp') ext = 'webp';
            if (mimeType === 'image/jpeg') ext = 'jpg';
            localPhotoExt = ext;
            setPhotoExt(ext);
          } catch (e) {
            console.error('Error fetching blob from uri:', e);
            Alert.alert('Gagal', 'Gagal memproses foto di browser');
            return;
          }
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('report_id', String(id));
        formData.append('type', 'closure');

        if (Platform.OS === 'web' && localPhotoBlob) {
          formData.append('file', localPhotoBlob, `upload.${localPhotoExt || 'jpg'}`);
        } else {
          // Native FormData append
          const filename = asset.uri.split('/').pop() || 'upload.jpg';
          const match = /\.(\w+)$/.exec(filename);
          const type = match ? `image/${match[1]}` : 'image/jpeg';
          
          formData.append('file', {
            uri: asset.uri,
            name: filename,
            type,
          } as any);
        }

        // 1. Upload photo
        await api.post('/attachments', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        // 2. Patch status
        await api.patch(`/crew/reports/${id}/status`, { status: 'completed' });
        
        setReport((prev) => (prev ? { ...prev, status: 'completed' } : prev));

      }
    } catch (err: any) {
      console.error('Submit error:', err);
      Alert.alert('Gagal', err?.error?.message || err?.message || 'Gagal mengirim bukti penyelesaian');
      setPhotoUri(null); // Revert photo on failure
    } finally {
      setUploading(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" />
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

  const showCrew = ['assigned', 'in_progress', 'completed'].includes(report.status);

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
          <Text style={styles.value}>Anda</Text>
        </View>
      )}

      <View style={styles.buttonContainer}>
        {report.status === 'assigned' && (
          <Button
            title="Mulai"
            onPress={handleStart}
            disabled={submitting}
          />
        )}
        {report.status === 'in_progress' && (
          <View testID="closure-photo-input">
            <Button
              title="Upload Bukti Penyelesaian"
              onPress={handleComplete}
              disabled={uploading}
            />
            {uploading && <ActivityIndicator style={{ marginTop: 10 }} />}
          </View>
        )}
        {report.status === 'completed' && (
          <View>
            <Text style={styles.completedText}>Laporan Selesai</Text>
            {photoUri && (
              <Image source={{ uri: photoUri }} style={styles.previewImage} />
            )}
          </View>
        )}
      </View>

      <View style={styles.backButtonContainer}>
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
  errorText: {
    color: '#ef4444',
    marginBottom: 16,
    fontSize: 16,
  },
  buttonContainer: {
    marginTop: 24,
  },
  backButtonContainer: {
    marginTop: 12,
  },
  completedText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#10b981',
    textAlign: 'center',
  },
  previewImage: {
    width: '100%',
    height: 200,
    marginTop: 10,
    borderRadius: 8,
    resizeMode: 'cover',
  },
});
