import React, { useEffect } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useBranchStore } from '../store/branch.store';
import { Colors } from '../theme';
import type { Sucursal } from '@amare/types';

export default function BranchSelectorScreen() {
  const router = useRouter();
  
  // 🌟 Extraemos todo lo necesario, incluyendo el 'loading' y 'fetchSucursales' de tu Zustand
  const { sucursales, seleccionada, loading, seleccionar, fetchSucursales } = useBranchStore();

  // 🔄 Forzar la carga de las sucursales al montar la pantalla
  useEffect(() => {
    fetchSucursales();
  }, []);

  function handleSelect(branch: Sucursal) {
    seleccionar(branch);
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/(tabs)');
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* HEADER */}
      <View style={styles.header}>
        <TouchableOpacity 
          onPress={() => router.canGoBack() ? router.back() : router.replace('/(tabs)')}
          style={styles.closeButton}
        >
          <Ionicons name="close" size={24} color={Colors?.text || '#111827'} />
        </TouchableOpacity>
        <Text style={styles.title}>Selecciona sucursal</Text>
        <View style={{ width: 24 }} />
      </View>

      {/* CONTENIDO INTERACTIVO */}
      {loading && sucursales.length === 0 ? (
        // Muestra el spinner de carga únicamente en la primera petición limpia
        <View style={styles.centerContainer}>
          <ActivityIndicator size="large" color={Colors?.accent || '#111827'} />
          <Text style={styles.loadingText}>Cargando sucursales...</Text>
        </View>
      ) : (
        <FlatList
          data={sucursales || []}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.list}
          
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="storefront-outline" size={48} color="#9CA3AF" />
              <Text style={styles.emptyText}>No hay sucursales disponibles</Text>
              <Text style={styles.emptySubtext}>
                Intenta de nuevo más tarde o verifica tu conexión de red.
              </Text>
            </View>
          }
          
          renderItem={({ item }) => {
            const isSelected = seleccionada?.id === item.id;
            return (
              <TouchableOpacity
                style={[styles.item, isSelected && styles.itemSelected]}
                onPress={() => handleSelect(item)}
                activeOpacity={0.7}
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
                    {item.descripcion ? (
                      <Text style={styles.itemAddress} numberOfLines={1}>
                        {item.descripcion}
                      </Text>
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
    backgroundColor: Colors?.background || '#FFFFFF' 
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
    color: Colors?.text || '#111827' 
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
    borderRadius: 16,
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
    flex: 1 
  },
  itemText: { 
    flex: 1,
    marginLeft: 8,
  },
  itemName: { 
    fontSize: 15, 
    fontWeight: '600', 
    color: Colors?.text || '#111827' 
  },
  itemNameSelected: { 
    color: Colors?.accent || '#111827',
    fontWeight: '700'
  },
  itemAddress: { 
    fontSize: 13, 
    color: Colors?.textMuted || '#6B7280', 
    marginTop: 2 
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
    fontWeight: '500'
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
  }
});