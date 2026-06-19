import React, { useEffect, useMemo, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Alert,
  Platform,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import { useCartStore } from '../store/cart.store';
import { useTableSessionStore } from '../store/table-session.store';
import { Button } from '../components/ui/Button';
import { EmptyState } from '../components/ui/EmptyState';
import { OrderTypeSelector } from '../components/shared/OrderTypeSelector';
import { Colors, Spacing, Typography, Shadows } from '../theme';
import { formatImageUrl } from '../services/api';
import { getDishById } from '../services/menu.service';
import type { CarritoItem } from '@amare/types';
import { useThemeColors } from '../store/theme.store';

const PLACEHOLDER_FOOD = require('../assets/placeholder-food.jpg');

function getSelectedExtras(item: CarritoItem) {
  return item.modificadores_seleccionados.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      precio: Number(opcion.precio_extra || 0),
    }))
  );
}

function getItemCostBreakdown(item: CarritoItem) {
  const selectedExtras = getSelectedExtras(item);
  const baseUnit = Number(item.platillo.precio || 0);
  const extrasUnit = selectedExtras.reduce((sum, extra) => sum + extra.precio, 0);
  const unitTotal = baseUnit + extrasUnit;
  const lineTotal = unitTotal * item.cantidad;

  return {
    selectedExtras,
    baseUnit,
    extrasUnit,
    unitTotal,
    lineTotal,
  };
}

export default function CartScreen() {
  const router = useRouter();
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const { items, removeItem, updateQty, clear, tipoPedido, setTipoPedido } = useCartStore();
  const tableSession = useTableSessionStore((s) => s.session);
  const displayTotal = useMemo(
    () => items.reduce((sum, item) => sum + getItemCostBreakdown(item).lineTotal, 0),
    [items]
  );

  function handleCheckout() {
    if (tipoPedido === 'eat_in' && !tableSession) {
      router.push({ pathname: '/table-scanner', params: { returnTo: '/cart' } });
      return;
    }

    router.replace('/checkout/order-type');
  }

  if (items.length === 0) {
    return (
      <SafeAreaView style={[styles.safe, { backgroundColor: theme.background }]}>
        <View style={[styles.header, { backgroundColor: theme.background }]}>
          <TouchableOpacity style={styles.iconButton} onPress={() => router.back()}>
            <Ionicons name="close" size={22} color="#111827" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Tu pedido</Text>
          <View style={{ width: 40 }} />
        </View>
        <EmptyState
          icon="bag-outline"
          title="Tu carrito está vacío"
          description="Agrega platillos desde el menú para comenzar tu pedido."
          actionLabel="Ver menú"
          onAction={() => router.back()}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={[styles.safe, { backgroundColor: theme.background }]}>
      {/* Cabecera Aireada y Elegante */}
      <View style={[styles.header, { backgroundColor: theme.background }]}>
        <TouchableOpacity style={styles.iconButton} onPress={() => router.back()}>
          <Ionicons name="close" size={22} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Tu pedido</Text>
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() =>
            Alert.alert('Vaciar carrito', '¿Seguro que deseas quitar todos los elementos?', [
              { text: 'Cancelar', style: 'cancel' },
              { text: 'Vaciar', style: 'destructive', onPress: () => { clear(); router.back(); } },
            ])
          }
        >
          <Text style={styles.clearText}>Vaciar</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={items}
        keyExtractor={(i) => i.id}
        contentContainerStyle={[styles.list, { paddingBottom: 140 + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0) }]}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => <CartItemRow item={item} onRemove={removeItem} onQty={updateQty} />}
        ListFooterComponent={
          <View style={styles.summaryContainer}>
            <View style={styles.summaryBox}>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Subtotal</Text>
                <Text style={styles.summaryValue}>${displayTotal.toFixed(2)}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Costos de gestión</Text>
                <Text style={styles.summaryValue}>$0.00</Text>
              </View>
              <View style={styles.divider} />
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Total final</Text>
                <Text style={styles.totalValue}>${displayTotal.toFixed(2)} MXN</Text>
              </View>
            </View>
          </View>
        }
      />

      {/* --- FOOTER EN UNA SOLA FILA --- */}
      <View
        style={[
          styles.footer,
          {
            backgroundColor: theme.background,
            paddingBottom: (Platform.OS === 'ios' ? 28 : 16) + Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 0),
          },
        ]}
      >
        <Button
          label="Seguir ordenando"
          onPress={() => router.back()}
          size="lg"
          variant="ghost"
          style={styles.secondaryButton}
        />

        <Button
          label="Continuar"
          onPress={handleCheckout}
          size="lg"
          style={styles.checkoutButton}
        />
      </View>
    </SafeAreaView>
  );
}

function CartItemRow({
  item,
  onRemove,
  onQty,
}: {
  item: CarritoItem;
  onRemove: (id: string) => void;
  onQty: (id: string, qty: number) => void;
}) {
  const [storedImageFailed, setStoredImageFailed] = useState(false);
  const [freshImageFailed, setFreshImageFailed] = useState(false);
  const {
    selectedExtras,
    baseUnit,
    extrasUnit,
    unitTotal,
    lineTotal,
  } = getItemCostBreakdown(item);

  const storedImageUrl = useMemo(
    () => formatImageUrl(item.platillo.imagen),
    [item.platillo.imagen]
  );

  const shouldFetchFreshImage = !storedImageUrl || storedImageFailed;
  const { data: freshPlatillo } = useQuery({
    queryKey: ['cart-item-image', item.platillo.restaurante_id, item.platillo.id],
    queryFn: () => getDishById(item.platillo.restaurante_id, item.platillo.id),
    enabled: shouldFetchFreshImage && Boolean(item.platillo.id),
    staleTime: 5 * 60 * 1000,
  });

  const freshImageUrl = useMemo(
    () => formatImageUrl(freshPlatillo?.imagen),
    [freshPlatillo?.imagen]
  );

  const imageKind = !storedImageFailed && storedImageUrl
    ? 'stored'
    : !freshImageFailed && freshImageUrl
      ? 'fresh'
      : 'placeholder';

  const imageSource =
    imageKind === 'stored'
      ? { uri: storedImageUrl }
      : imageKind === 'fresh'
        ? { uri: freshImageUrl }
        : PLACEHOLDER_FOOD;

  useEffect(() => {
    setStoredImageFailed(false);
    setFreshImageFailed(false);
  }, [item.id, item.platillo.imagen]);

  function handleImageError() {
    if (imageKind === 'stored') {
      setStoredImageFailed(true);
    } else if (imageKind === 'fresh') {
      setFreshImageFailed(true);
    }
  }

  return (
    <View style={styles.itemRow}>
      <Image
        source={imageSource}
        style={styles.itemImg}
        contentFit="cover"
        transition={200}
        onError={handleImageError}
      />

      <View style={styles.itemInfo}>
        <View style={styles.itemTopLine}>
          <Text style={styles.itemNombre} numberOfLines={1}>
            {item.platillo.nombre}
          </Text>
          <Text style={styles.itemSubtotal}>${lineTotal.toFixed(2)}</Text>
        </View>

        {item.notas ? (
          <View style={styles.notesBadge}>
            <Ionicons name="document-text-outline" size={12} color="#6B7280" />
            <Text style={styles.itemNotas} numberOfLines={1}>{item.notas}</Text>
          </View>
        ) : null}

        <View style={styles.priceBreakdown}>
          <View style={styles.breakdownRow}>
            <Text style={styles.breakdownLabel}>Plato base</Text>
            <Text style={styles.breakdownValue}>${baseUnit.toFixed(2)} c/u</Text>
          </View>

          {selectedExtras.length > 0 ? (
            <View style={styles.extrasList}>
              {selectedExtras.map((extra) => (
                <View key={extra.key} style={styles.breakdownRow}>
                  <Text style={styles.extraText} numberOfLines={1}>+ {extra.nombre}</Text>
                  <Text style={styles.extraPrice}>+${extra.precio.toFixed(2)} c/u</Text>
                </View>
              ))}
            </View>
          ) : null}

          <View style={[styles.breakdownRow, styles.unitTotalRow]}>
            <Text style={styles.unitTotalLabel}>Total del plato</Text>
            <Text style={styles.unitTotalValue}>${unitTotal.toFixed(2)} c/u</Text>
          </View>

          {item.cantidad > 1 ? (
            <View style={styles.breakdownRow}>
              <Text style={styles.breakdownLabel}>Total por {item.cantidad}</Text>
              <Text style={styles.breakdownValue}>${lineTotal.toFixed(2)}</Text>
            </View>
          ) : null}
        </View>

        <View style={styles.itemBottomLine}>
          <Text style={styles.itemPrecio}>
            {extrasUnit > 0 ? `Incluye extras por $${extrasUnit.toFixed(2)}` : 'Sin extras'}
          </Text>

          <View style={styles.qtyPillContainer}>
            <TouchableOpacity
              activeOpacity={0.6}
              onPress={() => item.cantidad > 1 ? onQty(item.id, item.cantidad - 1) : onRemove(item.id)}
              style={styles.qtyAction}
            >
              <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={14} color="#111827" />
            </TouchableOpacity>

            <Text style={styles.qtyNumber}>{item.cantidad}</Text>

            <TouchableOpacity
              activeOpacity={0.6}
              onPress={() => onQty(item.id, item.cantidad + 1)}
              style={styles.qtyAction}
            >
              <Ionicons name="add" size={14} color="#111827" />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FFFFFF' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 12,
  },
  iconButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    letterSpacing: -0.3,
  },
  clearText: {
    color: '#EF4444',
    fontSize: 14,
    fontWeight: '600',
    paddingHorizontal: 8,
  },
  list: { paddingBottom: 140 },

  typeRow: {
    paddingTop: 16,
    paddingBottom: 24,
    gap: 12,
  },
  sectionLabel: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    paddingHorizontal: Spacing.base || 16,
  },
  selectorWrapper: {
    paddingHorizontal: 4,
  },

  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    paddingHorizontal: Spacing.base || 16,
    paddingVertical: 18,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  itemImg: {
    width: 76,
    height: 76,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
  },
  itemInfo: { flex: 1, justifyContent: 'center', minHeight: 76, gap: 6 },
  itemTopLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemNombre: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
    flex: 1,
    marginRight: 8,
  },
  itemSubtotal: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  notesBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#F3F4F6',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 6,
    alignSelf: 'flex-start',
    maxWidth: '85%',
  },
  itemNotas: {
    fontSize: 11,
    color: '#6B7280',
    fontWeight: '500',
  },
  priceBreakdown: {
    gap: 4,
    paddingRight: 4,
    marginTop: 2,
  },
  breakdownRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  breakdownLabel: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '600',
  },
  breakdownValue: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  extrasList: {
    gap: 3,
  },
  extraText: {
    flex: 1,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
  extraPrice: {
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '800',
  },
  unitTotalRow: {
    marginTop: 2,
    paddingTop: 5,
    borderTopWidth: 1,
    borderTopColor: '#EEF0F4',
  },
  unitTotalLabel: {
    fontSize: 12,
    color: '#111827',
    fontWeight: '800',
  },
  unitTotalValue: {
    fontSize: 12,
    color: '#111827',
    fontWeight: '900',
  },
  itemBottomLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemPrecio: { fontSize: 13, color: '#9CA3AF', fontWeight: '500' },

  qtyPillContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    borderRadius: 20,
    padding: 3,
  },
  qtyAction: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
    shadowOpacity: 0.05,
  },
  qtyNumber: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
    minWidth: 28,
    textAlign: 'center',
  },

  summaryContainer: {
    padding: Spacing.base || 16,
    paddingTop: 32,
  },
  summaryBox: {
    backgroundColor: '#F9FAFB',
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    gap: 12,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  summaryLabel: { fontSize: 14, color: '#6B7280', fontWeight: '500' },
  summaryValue: { fontSize: 14, fontWeight: '600', color: '#111827' },
  divider: {
    height: 1,
    backgroundColor: '#E5E7EB',
    marginVertical: 4,
    borderStyle: 'dashed',
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  totalLabel: { fontSize: 15, fontWeight: '700', color: '#111827' },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary || '#111827' },

  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: Spacing.base || 16,
    paddingTop: 16,
    paddingBottom: Platform.OS === 'ios' ? 36 : 20,
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
    gap: 12,
    ...Shadows.md,
  },
  checkoutButton: {
    flex: 1,
    borderRadius: 14,
  },
  secondaryButton: {
    flex: 1,
    borderRadius: 14,
    backgroundColor: '#FFFFFF',
    borderWidth: 2,
    borderColor: Colors.primary || '#111827',
  },
});
