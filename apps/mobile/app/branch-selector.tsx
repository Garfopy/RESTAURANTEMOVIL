import React from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useBranchStore } from '../store/branch.store';
import { Colors } from '../theme';
import type { Sucursal } from '@amare/types';

export default function BranchSelectorScreen() {
  const router = useRouter();
  const { sucursales, seleccionada, seleccionar } = useBranchStore();

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
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.canGoBack() ? router.back() : router.replace('/(tabs)')}>
          <Ionicons name="close" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.title}>Selecciona sucursal</Text>
        <View style={{ width: 24 }} />
      </View>

      <FlatList
        data={sucursales}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => {
          const isSelected = seleccionada?.id === item.id;
          return (
            <TouchableOpacity
              style={[styles.item, isSelected && styles.itemSelected]}
              onPress={() => handleSelect(item)}
            >
              <View style={styles.itemContent}>
                <Ionicons
                  name="location-sharp"
                  size={20}
                  color={isSelected ? Colors.accent : Colors.textMuted}
                />
                <View style={styles.itemText}>
                  <Text style={[styles.itemName, isSelected && styles.itemNameSelected]}>
                    {item.nombre}
                  </Text>
                  {item.direccion ? (
                    <Text style={styles.itemAddress} numberOfLines={1}>
                      {item.direccion}
                    </Text>
                  ) : null}
                </View>
              </View>
              {isSelected && (
                <Ionicons name="checkmark-circle" size={22} color={Colors.accent} />
              )}
            </TouchableOpacity>
          );
        }}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  title: { fontSize: 17, fontWeight: '600', color: Colors.text },
  list: { padding: 16, gap: 8 },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  itemSelected: { borderColor: Colors.accent },
  itemContent: { flexDirection: 'row', alignItems: 'center', gap: 12, flex: 1 },
  itemText: { flex: 1 },
  itemName: { fontSize: 15, fontWeight: '500', color: Colors.text },
  itemNameSelected: { color: Colors.accent },
  itemAddress: { fontSize: 13, color: Colors.textMuted, marginTop: 2 },
});
