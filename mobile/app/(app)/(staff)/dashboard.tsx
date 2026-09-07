import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  RefreshControl,
  Button,
  Modal,
} from 'react-native';
import { useAuth } from '../../../src/hooks/useAuth';
import { api } from '../../../src/services/api';
import { StatusBadge, ReportStatus } from '../../../src/components/StatusBadge';

interface Report {
  id: number;
  description: string;
  status: ReportStatus;
  created_at: string;
  category: {
    name: string;
  };
}

interface CrewUser {
  id: number;
  name: string;
  email: string;
}

interface ReportsResponse {
  data: Report[];
}

export default function StaffDashboard() {
  const { logout } = useAuth();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [modalVisible, setModalVisible] = useState(false);
  const [crewList, setCrewList] = useState<CrewUser[]>([]);
  const [crewLoading, setCrewLoading] = useState(false);
  const [selectedCrewId, setSelectedCrewId] = useState<number | null>(null);
  const [assigningReportId, setAssigningReportId] = useState<number | null>(null);
  const [assigning, setAssigning] = useState(false);

  const fetchReports = async (isRefresh: boolean = false) => {
    try {
      if (!isRefresh) setLoading(true);
      setError(null);
      const response = await api.get<ReportsResponse>('/reports?status=submitted');
      setReports(response.data.data);
    } catch {
      setError('Gagal memuat laporan');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchReports(true);
  }, []);

  const handleTugaskan = async (reportId: number) => {
    setAssigningReportId(reportId);
    setSelectedCrewId(null);
    setModalVisible(true);

    if (crewList.length === 0) {
      setCrewLoading(true);
      try {
        const response = await api.get<CrewUser[]>('/staff/users?role=crew');
        setCrewList(response.data);
      } catch {
        setCrewList([]);
      } finally {
        setCrewLoading(false);
      }
    }
  };

  const handleConfirmAssign = async () => {
    if (selectedCrewId === null || assigningReportId === null) return;

    setAssigning(true);
    try {
      await api.post(`/staff/reports/${assigningReportId}/assign`, {
        crew_user_id: selectedCrewId,
      });
      setReports((prev) => prev.filter((r) => r.id !== assigningReportId));
      setModalVisible(false);
      setSelectedCrewId(null);
      setAssigningReportId(null);
    } catch {
      setError('Gagal menugaskan kru');
    } finally {
      setAssigning(false);
    }
  };

  const closeModal = () => {
    setModalVisible(false);
    setSelectedCrewId(null);
    setAssigningReportId(null);
  };

  const renderItem = ({ item }: { item: Report }) => (
    <View testID="report-card" style={styles.card}>
      <Text style={styles.description} numberOfLines={2}>{item.description}</Text>
      <Text style={styles.category}>{item.category.name}</Text>
      <Text style={styles.date}>{new Date(item.created_at).toLocaleDateString('id-ID')}</Text>
      <StatusBadge status={item.status} />
      <TouchableOpacity
        style={styles.assignButton}
        onPress={() => handleTugaskan(item.id)}
      >
        <Text style={styles.assignButtonText}>Tugaskan</Text>
      </TouchableOpacity>
    </View>
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
      <View style={styles.header}>
        <Text style={styles.title}>Dashboard Petugas</Text>
        <Button title="Keluar" onPress={logout} />
      </View>
      <FlatList
        data={reports}
        keyExtractor={(item) => item.id.toString()}
        renderItem={renderItem}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <Text style={styles.emptyText}>Belum ada laporan yang perlu ditugaskan</Text>
        }
        contentContainerStyle={styles.listContainer}
      />

      <Modal
        visible={modalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={closeModal}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Pilih Petugas</Text>

            {crewLoading ? (
              <ActivityIndicator size="large" style={styles.modalLoading} />
            ) : crewList.length === 0 ? (
              <Text style={styles.emptyText}>Tidak ada kru tersedia</Text>
            ) : (
              <FlatList
                data={crewList}
                keyExtractor={(item) => item.id.toString()}
                renderItem={({ item }) => (
                  <TouchableOpacity
                    testID="crew-option"
                    style={[
                      styles.crewOption,
                      selectedCrewId === item.id && styles.crewOptionSelected,
                    ]}
                    onPress={() => setSelectedCrewId(item.id)}
                  >
                    <Text style={styles.crewName}>{item.name}</Text>
                    <Text style={styles.crewEmail}>{item.email}</Text>
                  </TouchableOpacity>
                )}
              />
            )}

            <View style={styles.modalActions}>
              <Button title="Batal" onPress={closeModal} disabled={assigning} />
              <View style={styles.modalActionSpacer} />
              <Button
                title="Tugaskan"
                onPress={handleConfirmAssign}
                disabled={selectedCrewId === null || assigning}
              />
            </View>
          </View>
        </View>
      </Modal>
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
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1f2937',
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
    color: '#1f2937',
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
  assignButton: {
    marginTop: 12,
    backgroundColor: '#2563eb',
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  assignButtonText: {
    color: 'white',
    fontWeight: '600',
    fontSize: 14,
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
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
  },
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    width: '85%',
    maxHeight: '70%',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 16,
    textAlign: 'center',
    color: '#1f2937',
  },
  modalLoading: {
    paddingVertical: 24,
  },
  crewOption: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    marginBottom: 8,
  },
  crewOptionSelected: {
    borderColor: '#2563eb',
    backgroundColor: '#eff6ff',
  },
  crewName: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1f2937',
  },
  crewEmail: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 16,
  },
  modalActionSpacer: {
    width: 12,
  },
});
