import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export type ReportStatus = 'submitted' | 'assigned' | 'in_progress' | 'completed';

interface StatusBadgeProps {
  status: ReportStatus;
}

const statusMapping = {
  submitted: { label: 'Dikirim', color: '#9CA3AF' },
  assigned: { label: 'Ditugaskan', color: '#3B82F6' },
  in_progress: { label: 'Dikerjakan', color: '#F59E0B' },
  completed: { label: 'Selesai', color: '#10B981' },
};

export function StatusBadge({ status }: StatusBadgeProps) {
  const config = statusMapping[status] || statusMapping.submitted;
  
  return (
    <View testID="status-badge" style={[styles.badge, { backgroundColor: config.color }]}>
      <Text style={styles.text}>{config.label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  text: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: 'bold',
  },
});
