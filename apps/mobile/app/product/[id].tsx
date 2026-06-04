import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  SafeAreaView,
} from 'react-native';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { formatImageUrl } from '../../services/api';
import { useDish } from '../../hooks/useMenu';
import { useCartStore } from '../../store/cart.store';
import { useFavoritesStore } from '../../store/favorites.store';
import { Button } from '../../components/ui/Button';
import { Skeleton } from '../../components/ui/Skeleton';
import { Colors, Spacing, Typography, Shadows } from '../../theme';
import type { ModificadorSeleccionado, OpcionModificador } from '@amare/types';

export default function ProductScreen() {
  const router = useRouter();
  const { id, restauranteId } = useLocalSearchParams<{ id: string; restauranteId: string }>();

  const { data: platillo, isLoading } = useDish(Number(restauranteId), Number(id));
  const addItem = useCartStore((s) => s.addItem);
  const { isFavorite, toggle: toggleFav } = useFavoritesStore();
  const [cantidad, setCantidad] = useState(1);
  const [notas, setNotas] = useState('');
  const [modsSel, setModsSel] = useState<ModificadorSeleccionado[]>([]);

  if (isLoading || !platillo) {
    return (
      <SafeAreaView style={styles.safe}>
        <Skeleton height={280} borderRadius={0} />
        <View style={{ padding: Spacing.base, gap: 12 }}>
          <Skeleton height={28} width="60%" />
          <Skeleton height={16} />
          <Skeleton height={16} width="80%" />
        </View>
      </SafeAreaView>
    );
  }

  function toggleOpcion(modId: number, modNombre: string, opcion: OpcionModificador, tipo: 'radio' | 'checkbox') {
    const nuevoItem: ModificadorSeleccionado = {
      modificador_id: modId,
      modificador_nombre: modNombre,
      opciones: [{ opcion_id: opcion.id, opcion_nombre: opcion.nombre, precio_extra: opcion.precio_extra }],
    };
    setModsSel((prev) => {
      if (tipo === 'radio') {
        return [...prev.filter((m) => m.modificador_id !== modId), nuevoItem];
      }
      // checkbox
      const existing = prev.find((m) => m.modificador_id === modId);
      if (existing) {
        const yaSelec = existing.opciones.some((o) => o.opcion_id === opcion.id);
        const nuevasOpciones = yaSelec
          ? existing.opciones.filter((o) => o.opcion_id !== opcion.id)
          : [...existing.opciones, { opcion_id: opcion.id, opcion_nombre: opcion.nombre, precio_extra: opcion.precio_extra }];
        if (nuevasOpciones.length === 0) return prev.filter((m) => m.modificador_id !== modId);
        return [
          ...prev.filter((m) => m.modificador_id !== modId),
          { ...existing, opciones: nuevasOpciones },
        ];
      }
      return [...prev, nuevoItem];
    });
  }

  function handleAddToCart() {
    if (!platillo) return;
    addItem(platillo, cantidad, modsSel, notas.trim());
    router.back();
  }

  const isFav = isFavorite(platillo.id);

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={{ paddingBottom: 120 }}>
        {/* Imagen */}
        <View style={styles.imageContainer}>
          <Image
            source={formatImageUrl(platillo.imagen) ?? require('../../assets/placeholder-food.jpg')}
            style={styles.image}
            contentFit="cover"
          />
          <TouchableOpacity style={styles.backBtn} onPress={() => router.back()}>
            <Ionicons name="arrow-back" size={22} color={Colors.text} />
          </TouchableOpacity>
          <TouchableOpacity style={styles.favBtn} onPress={() => toggleFav(platillo.id)}>
            <Ionicons
              name={isFav ? 'heart' : 'heart-outline'}
              size={22}
              color={isFav ? Colors.error : Colors.text}
            />
          </TouchableOpacity>
        </View>

        {/* Info */}
        <View style={styles.info}>
          <Text style={styles.nombre}>{platillo.nombre}</Text>
          {platillo.descripcion && (
            <Text style={styles.descripcion}>{platillo.descripcion}</Text>
          )}
          <Text style={styles.precio}>${platillo.precio.toFixed(2)}</Text>

          {/* Modificadores */}
          {platillo.modificadores?.map((mod) => (
            <View key={mod.id} style={styles.modGroup}>
              <Text style={styles.modTitle}>
                {mod.nombre}
                {mod.requerido && <Text style={styles.req}> *</Text>}
              </Text>
              <Text style={styles.modSubtitle}>
                {mod.tipo === 'radio' ? 'Elige una opción' : 'Selecciona las que quieras'}
              </Text>
              {mod.opciones.map((opcion) => {
                const selMod = modsSel.find((m) => m.modificador_id === mod.id);
                const isSelected = selMod?.opciones.some((o) => o.opcion_id === opcion.id) ?? false;
                return (
                  <TouchableOpacity
                    key={opcion.id}
                    style={styles.opcionRow}
                    onPress={() => toggleOpcion(mod.id, mod.nombre, opcion, mod.tipo as 'radio' | 'checkbox')}
                  >
                    <View style={[styles.checkBox, isSelected && styles.checkBoxActive]}>
                      {isSelected && <Ionicons name="checkmark" size={12} color={Colors.white} />}
                    </View>
                    <Text style={styles.opcionNombre}>{opcion.nombre}</Text>
                    {(opcion.precio_extra ?? 0) > 0 && (
                      <Text style={styles.opcionPrecio}>+${opcion.precio_extra!.toFixed(2)}</Text>
                    )}
                  </TouchableOpacity>
                );
              })}
            </View>
          ))}
        </View>
      </ScrollView>

      {/* Footer CTA */}
      <View style={styles.footer}>
        <View style={styles.qtyRow}>
          <TouchableOpacity
            onPress={() => setCantidad((c) => Math.max(1, c - 1))}
            style={styles.qtyBtn}
          >
            <Ionicons name="remove" size={18} color={Colors.text} />
          </TouchableOpacity>
          <Text style={styles.qty}>{cantidad}</Text>
          <TouchableOpacity onPress={() => setCantidad((c) => c + 1)} style={styles.qtyBtn}>
            <Ionicons name="add" size={18} color={Colors.text} />
          </TouchableOpacity>
        </View>
        <Button
          label={`Agregar · $${(platillo.precio * cantidad).toFixed(2)}`}
          onPress={handleAddToCart}
          style={{ flex: 1 }}
          size="lg"
          disabled={!platillo.disponible}
        />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  imageContainer: { width: '100%', height: 280, position: 'relative' },
  image: { width: '100%', height: '100%' },
  backBtn: {
    position: 'absolute',
    top: 16,
    left: 16,
    backgroundColor: Colors.white,
    borderRadius: 20,
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
  },
  favBtn: {
    position: 'absolute',
    top: 16,
    right: 16,
    backgroundColor: Colors.white,
    borderRadius: 20,
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
  },
  info: { padding: Spacing.base, gap: Spacing.sm },
  nombre: { fontFamily: 'PlayfairDisplay_700Bold', fontSize: 26, color: Colors.text },
  descripcion: { ...Typography.body, color: Colors.textMuted, lineHeight: 22 },
  precio: { ...Typography.priceLG, color: Colors.primary, fontWeight: '700' },
  modGroup: {
    marginTop: Spacing.base,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
    gap: 6,
  },
  modTitle: { fontSize: 15, fontWeight: '700', color: Colors.text },
  req: { color: Colors.error },
  modSubtitle: { fontSize: 12, color: Colors.textMuted, marginBottom: 4 },
  opcionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 6,
  },
  checkBox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: Colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkBoxActive: { backgroundColor: Colors.primary, borderColor: Colors.primary },
  opcionNombre: { flex: 1, fontSize: 14, color: Colors.text },
  opcionPrecio: { fontSize: 13, color: Colors.textMuted, fontWeight: '600' },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    paddingBottom: 28,
    backgroundColor: Colors.background,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
    ...Shadows.md,
  },
  qtyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    paddingHorizontal: 12,
  },
  qtyBtn: { padding: 6 },
  qty: { fontSize: 16, fontWeight: '700', color: Colors.text, minWidth: 24, textAlign: 'center' },
});
