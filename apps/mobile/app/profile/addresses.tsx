import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { apiClient } from '../../services/api';
import { AddressModal } from '../../components/modals/AddressModal';
import { Colors, Spacing, Typography } from '../../theme';
import type { Direccion } from '@amare/types';

export default function AddressesScreen() {
  const router = useRouter();
  const qc = useQueryClient();

  const [modalVisible, setModalVisible] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Direccion | null>(null);

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

  const openModal = (address?: Direccion) => {
    setEditingAddress(address || null);
    setModalVisible(true);
  };

  const handleCloseModal = () => {
    setModalVisible(false);
    setEditingAddress(null);
  };

  function confirmDelete(id: number) {
    Alert.alert('Eliminar dirección', '¿Estás seguro de que deseas eliminar esta dirección?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Eliminar', style: 'destructive', onPress: () => deleteMut.mutate(id) },
    ]);
  }

  // Configuración para agrandar el área de toque de los botones pequeños
  const hitSlopBounds = { top: 15, bottom: 15, left: 15, right: 15 };

  return (
    <SafeAreaView style={styles.safe}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} hitSlop={hitSlopBounds}>
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Mis direcciones</Text>
        <TouchableOpacity onPress={() => openModal()} hitSlop={hitSlopBounds}>
          <Ionicons name="add" size={26} color={Colors.accent} />
        </TouchableOpacity>
      </View>

      {/* Contenido Principal */}
      {isLoading ? (
        <View style={styles.loaderContainer}>
          <ActivityIndicator size="large" color={Colors.accent} />
        </View>
      ) : (
        <FlatList
          data={addresses ?? []}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={[
            styles.list,
            addresses?.length === 0 && { flexGrow: 1, justifyContent: 'center' }
          ]}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <View style={styles.emptyIconCircle}>
                <Ionicons name="location-outline" size={40} color={Colors.textMuted} />
              </View>
              <Text style={styles.emptyTitle}>Aún no tienes direcciones</Text>
              <Text style={styles.emptySubtext}>Toca el botón + superior para agregar una nueva dirección de entrega.</Text>
            </View>
          }
          renderItem={({ item }) => (
            <View style={styles.card}>
              <TouchableOpacity
                style={styles.cardContent}
                onPress={() => openModal(item)}
                activeOpacity={0.7}
              >
                {/* Ícono de la dirección (Si da error "road-outline" en otro lado, cámbialo por "map-outline" o "location") */}
                <View style={styles.cardIcon}>
                  <Ionicons name="location" size={18} color={Colors.accent} />
                </View>
                
                <View style={styles.cardBody}>
                  <Text style={styles.alias} numberOfLines={1}>
                    {item.alias || 'Dirección'}
                  </Text>
                  <Text style={styles.calle} numberOfLines={2}>
                    {item.calle}
                  </Text>
                  {item.colonia && (
                    <Text style={styles.ref} numberOfLines={1}>
                      {item.colonia}
                    </Text>
                  )}
                  {item.instrucciones && (
                    <Text style={styles.instrucciones} numberOfLines={2}>
                      📌 {item.instrucciones}
                    </Text>
                  )}
                </View>
              </TouchableOpacity>

              <TouchableOpacity 
                onPress={() => confirmDelete(item.id)} 
                hitSlop={hitSlopBounds}
                style={styles.deleteButton}
              >
                <Ionicons name="trash-outline" size={20} color={Colors.error} />
              </TouchableOpacity>
            </View>
          )}
        />
      )}

      <AddressModal
        visible={modalVisible}
        onDismiss={handleCloseModal}
        address={editingAddress}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { 
    flex: 1, 
    backgroundColor: Colors.background 
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    backgroundColor: Colors.surface, // Le da más cuerpo al header
  },
  headerTitle: { 
    ...Typography.h2, 
    fontWeight: '700', 
    color: Colors.text 
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  list: { 
    padding: Spacing.base, 
    gap: Spacing.md // Un poco más de espacio entre tarjetas da aire visual
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: Spacing.xl,
  },
  emptyIconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: Colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: Spacing.md,
  },
  emptyTitle: {
    textAlign: 'center',
    color: Colors.text,
    ...Typography.body,
    fontWeight: '700',
    fontSize: 18,
  },
  emptySubtext: {
    textAlign: 'center',
    color: Colors.textMuted,
    ...Typography.body,
    fontSize: 14,
    marginTop: Spacing.xs,
    lineHeight: 20,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'flex-start', // Cambio clave: alinea arriba por si el texto crece
    justifyContent: 'space-between',
    backgroundColor: Colors.surface,
    borderRadius: 16, // Bordes un poco más modernos
    padding: Spacing.md,
    // Sombra sutil para darle profundidad si es un tema claro
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  cardContent: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: Spacing.md,
  },
  cardIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: `${Colors.accent}15`, // Color accent con 15% de opacidad de fondo
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
    marginTop: 2,
  },
  cardBody: { 
    flex: 1,
    paddingRight: Spacing.sm,
  },
  alias: { 
    fontWeight: '700', 
    fontSize: 16, 
    color: Colors.text 
  },
  calle: { 
    fontSize: 14, 
    color: Colors.textSecondary, 
    marginTop: 4,
    lineHeight: 18 
  },
  ref: { 
    fontSize: 13, 
    color: Colors.textMuted, 
    marginTop: 2 
  },
  instrucciones: { 
    fontSize: 12, 
    color: Colors.textMuted, 
    marginTop: 6, 
    fontStyle: 'italic',
    backgroundColor: Colors.background,
    padding: 6,
    borderRadius: 6,
  },
  deleteButton: {
    paddingTop: 4,
  }
});
