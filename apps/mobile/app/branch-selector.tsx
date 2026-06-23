import React, { useEffect } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, Alert } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useBranchStore } from '../store/branch.store';
import { useCartStore } from '../store/cart.store';
import { Colors } from '../theme';
import type { Sucursal, TipoPedido } from '@amare/types';

export default function BranchSelectorScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ tipoPedido?: TipoPedido }>();
  const tipoPedido = params.tipoPedido;
  const { sucursales, seleccionada, loading, seleccionar, fetchSucursales } = useBranchStore();
  const { itemCount, restauranteId, clear } = useCartStore();

  useEffect(() => {
    fetchSucursales();
  }, [fetchSucursales]);

  const filteredSucursales = tipoPedido
    ? sucursales.filter((branch) => branch.tipos_entrega?.includes(tipoPedido))
    : sucursales;

  function close() {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/(tabs)');
    }
  }

  function completeSelect(branch: Sucursal) {
    seleccionar(branch);
    close();
  }

  function handleSelect(branch: Sucursal) {
    if (itemCount > 0 && restauranteId !== null && restauranteId !== branch.id) {
      Alert.alert(
        'Cambiar sucursal',
        'Tu carrito tiene platillos de otra sucursal. Para cambiarla necesitamos vaciarlo.',
        [
          { text: 'Cancelar', style: 'cancel' },
          {
            text: 'Vaciar y cambiar',
            style: 'destructive',
            onPress: () => {
              clear();
              completeSelect(branch);
            },
          },
        ]
      );
      return;
    }

    completeSelect(branch);
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity
          onPress={close}
          style={styles.closeButton}
          accessibilityLabel="Cerrar"
          accessibilityRole="button"
          testID="close-btn"
        >
          <Ionicons name="close" size={24} color={Colors?.text || '#111827'} />
        </TouchableOpacity>
        <Text style={styles.title}>Selecciona sucursal</Text>
        <View style={{ width: 24 }} />
      </View>

      {loading && sucursales.length === 0 ? (
        <View style={styles.centerContainer}>
          <ActivityIndicator size="large" color={Colors?.accent || '#111827'} />
          <Text style={styles.loadingText}>Cargando sucursales...</Text>
        </View>
      ) : (
        <FlatList
          data={filteredSucursales}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.list}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="storefront-outline" size={48} color="#9CA3AF" />
              <Text style={styles.emptyText}>No hay sucursales disponibles</Text>
              <Text style={styles.emptySubtext}>
                Intenta de nuevo más tarde o elige otro tipo de pedido.
              </Text>
            </View>
          }
          renderItem={({ item }) => {
            const isSelected = seleccionada?.id === item.id;
            const subtitle = item.direccion || item.descripcion || 'Sucursal';

            return (
              <TouchableOpacity
                style={[styles.item, isSelected && styles.itemSelected]}
                onPress={() => handleSelect(item)}
                activeOpacity={0.7}
                accessibilityLabel={`Seleccionar sucursal ${item.nombre}`}
                accessibilityRole="radio"
                accessibilityState={{ selected: isSelected }}
                accessibilityHint={subtitle}
                testID={`branch-${item.id}`}
              >
                <View style={styles.itemContent}>
                  <Ionicons
                    name="location-sharp"
                    size={20}
                    color={isSelected ? (Colors?.accent || '#111827') : (Colors?.textMuted || '#9CA3AF')}
                  />
                  <View style={styles.itemText}>
                    <Text style={[styles.itemName, isSelected && styles.itemNameSelected]}>
                      {item.nombre}
                    </Text>
                    <Text style={styles.itemAddress} numberOfLines={1}>
                      {subtitle}
                    </Text>
                    {item.distancia_km != null ? (
                      <Text style={styles.itemDistance}>{item.distancia_km.toFixed(2)} km de ti</Text>
                    ) : null}
                  </View>
                </View>
                {isSelected && (
                  <Ionicons name="checkmark-circle" size={22} color={Colors?.accent || '#111827'} />
                )}
              </TouchableOpacity>
            );
          }}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors?.background || '#FFFFFF',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors?.border || '#E5E7EB',
  },
  closeButton: {
    padding: 4,
  },
  title: {
    fontSize: 17,
    fontWeight: '700',
    color: Colors?.text || '#111827',
  },
  list: {
    padding: 16,
    flexGrow: 1,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    backgroundColor: Colors?.surface || '#F3F4F6',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors?.border || '#E5E7EB',
    marginBottom: 10,
  },
  itemSelected: {
    borderColor: Colors?.accent || '#111827',
  },
  itemContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  itemText: {
    flex: 1,
    marginLeft: 8,
  },
  itemName: {
    fontSize: 15,
    fontWeight: '600',
    color: Colors?.text || '#111827',
  },
  itemNameSelected: {
    color: Colors?.accent || '#111827',
    fontWeight: '700',
  },
  itemAddress: {
    fontSize: 13,
    color: Colors?.textMuted || '#6B7280',
    marginTop: 2,
  },
  itemDistance: {
    fontSize: 12,
    color: Colors?.primary || '#C8102E',
    marginTop: 4,
    fontWeight: '600',
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 14,
    color: '#6B7280',
    fontWeight: '500',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 40,
    paddingTop: 80,
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#374151',
    marginTop: 12,
    textAlign: 'center',
  },
  emptySubtext: {
    fontSize: 13,
    color: '#9CA3AF',
    marginTop: 4,
    textAlign: 'center',
  },
});
