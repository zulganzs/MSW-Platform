import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Platform } from 'react-native';
import { useRouter } from 'expo-router';
import MapWidget, { MarkerPoint } from '../../../src/components/MapWidget';
import { api } from '../../../src/services/api';
import * as Location from 'expo-location';
import { useAuth } from '../../../src/hooks/useAuth';
import { StatusBadge } from '../../../src/components/StatusBadge';

interface ReportMarker extends MarkerPoint {
  id: string | number;
  reportStatus: string;
}

export default function MapScreen() {
  const [reports, setReports] = useState<ReportMarker[]>([]);
  const [loading, setLoading] = useState(true);
  const [center, setCenter] = useState<MarkerPoint>({ latitude: -6.2, longitude: 106.8 });
  const router = useRouter();
  const { token } = useAuth();

  useEffect(() => {
    fetchLocation();
    fetchReports();
  }, [token]);

  const fetchLocation = async () => {
    try {
      if (Platform.OS === 'web') {
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(
            (position) => {
              setCenter({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
              });
            },
            () => {}
          );
        }
      } else {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (status === 'granted') {
          const location = await Location.getCurrentPositionAsync({});
          setCenter({
            latitude: location.coords.latitude,
            longitude: location.coords.longitude
          });
        }
      }
    } catch (e) {
      // fallback to default
    }
  };

  const fetchReports = async () => {
    try {
      setLoading(true);
      const res = await api.get('/reports', { params: { visibility: 'public' } });
      const data = res.data?.data || res.data || [];
      
      const markers = data.map((report: any) => ({
        id: report.id,
        latitude: report.latitude,
        longitude: report.longitude,
        title: report.category?.name || 'Laporan',
        description: report.status,
        reportStatus: report.status,
      }));
      
      setReports(markers);
    } catch (error) {
      // Silently handle error, show empty map
    } finally {
      setLoading(false);
    }
  };

  const handleMarkerPress = (marker: MarkerPoint) => {
    router.push(`/reports/${marker.id as string}`);
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#3b82f6" />
        <Text style={styles.loadingText}>Memuat peta publik...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {reports.length === 0 && (
        <View style={styles.emptyOverlay}>
          <Text style={styles.emptyText}>Belum ada laporan publik</Text>
        </View>
      )}
      <MapWidget 
        markers={reports} 
        initialCenter={center}
        onMarkerPress={handleMarkerPress}
        style={styles.map} 
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  map: {
    flex: 1,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
  },
  loadingText: {
    marginTop: 12,
    color: '#64748b',
  },
  emptyOverlay: {
    position: 'absolute',
    top: 20,
    left: 20,
    right: 20,
    backgroundColor: 'rgba(255,255,255,0.9)',
    padding: 12,
    borderRadius: 8,
    zIndex: 10,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  emptyText: {
    color: '#334155',
    fontWeight: '500',
  }
});
