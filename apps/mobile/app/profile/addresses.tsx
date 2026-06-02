import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Alert,
  SafeAreaView,
  ActivityIndicator,
} from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { apiClient } from '../../services/api';
import { Colors, Spacing, Typography } from '../../theme';
import type { Direccion } from '@amare/types';

export default function AddressesScreen() {
  const router = useRouter();
  const qc = useQueryClient();

  const { data: addresses, isLoading } = useQuery<Direccion[]>({
    queryKey: ['addresses'],
    queryFn: async () => {
      const { data } = await apiClient.get('/profile/addresses');
      return data.data;
    },
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/profile/addresses/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['addresses'] }),
  });

  function confirmDelete(id: number) {
    Alert.alert('Eliminar dirección', '¿Seguro?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Eliminar', style: 'destructive', onPress: () => deleteMut.mutate(id) },
    ]);
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Mis direcciones</Text>
        <View style={{ width: 24 }} />
      </View>

      {isLoading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={Colors.accent} />
      ) : (
        <FlatList
          data={addresses ?? []}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <Text style={styles.empty}>Aún no tienes direcciones guardadas.</Text>
          }
          renderItem={({ item }) => (
            <View style={styles.card}>
              <View style={styles.cardIcon}>
                <Ionicons name="location" size={20} color={Colors.accent} />
              </View>
              <View style={styles.cardBody}>
                <Text style={styles.alias}>{item.alias ?? 'Dirección'}</Text>
                <Text style={styles.calle}>{item.calle}</Text>
                {item.instrucciones && (
                  <Text style={styles.ref}>{item.instrucciones}</Text>
                )}
              </View>
              <TouchableOpacity onPress={() => confirmDelete(item.id)}>
                <Ionicons name="trash-outline" size={20} color={Colors.error} />
              </TouchableOpacity>
            </View>
          )}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h2, fontWeight: '700', color: Colors.text },
  list: { padding: Spacing.base, gap: Spacing.sm },
  empty: { textAlign: 'center', color: Colors.textMuted, marginTop: 40, ...Typography.body },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
  },
  cardIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: Colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardBody: { flex: 1 },
  alias: { fontWeight: '700', fontSize: 15, color: Colors.text },
  calle: { fontSize: 13, color: Colors.textSecondary, marginTop: 2 },
  ref: { fontSize: 12, color: Colors.textMuted, marginTop: 1 },
});
