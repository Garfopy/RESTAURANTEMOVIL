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
import { useBranchConfigStore, useBranchStore } from '../store/branch.store';
import { Button } from '../components/ui/Button';
import { EmptyState } from '../components/ui/EmptyState';
import { Colors, Spacing, Shadows, FontFamily } from '../theme';
import { formatImageUrl } from '../services/api';
import { getDishById } from '../services/menu.service';
import { requireAuth } from '../services/auth-gate.service';
import { getBranchOpenStatus } from '../services/business-hours';
import type { CarritoItem } from '@amare/types';
import { useThemeColors } from '../store/theme.store';

const PLACEHOLDER_FOOD = require('../assets/placeholder-food.jpg');

function getSelectedExtras(item: CarritoItem) {
  return item.modificadores_seleccionados.flatMap((mod) =>
    mod.opciones.map((opcion) => ({
      key: `${mod.modificador_id}-${opcion.opcion_id}`,
      nombre: opcion.opcion_nombre,
      cantidad: Math.max(1, Number(opcion.cantidad ?? 1)),
      precio: Number(opcion.precio_extra || 0) * Math.max(1, Number(opcion.cantidad ?? 1)),
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
  const { items, removeItem, updateQty, clear, restauranteId } = useCartStore();
  const displayTotal = useMemo(
    () => items.reduce((sum, item) => sum + getItemCostBreakdown(item).lineTotal, 0),
    [items]
  );
  const config = useBranchConfigStore((state) =>
    state.branchId === restauranteId ? state.config : null
  );
  const pedidoMinimo = Number(config?.pedido_minimo ?? 0);
  const belowMinimum = pedidoMinimo > 0 && displayTotal < pedidoMinimo;
  const missingForMinimum = Math.max(0, pedidoMinimo - displayTotal);

  const sucursales = useBranchStore((state) => state.sucursales);
  const cartBranch = sucursales.find((s) => String(s.id) === String(restauranteId)) ?? null;
  const openStatus = getBranchOpenStatus(cartBranch);
  const checkoutBlocked = belowMinimum || !openStatus.isOpen;

  function handleCheckout() {
    if (checkoutBlocked) return;

    if (!requireAuth(router, {
      message: 'Crea tu cuenta para completar el pedido, guardar tus datos y darle seguimiento.',
      returnTo: '/checkout/order-type',
    })) {
      return;
    }

    router.replace('/checkout/order-type');
  }

  if (items.length === 0) {
    return (
      <SafeAreaView style={[styles.safe, { backgroundColor: theme.background }]}>
        <View style={[styles.header, { backgroundColor: theme.background }]}>
          <TouchableOpacity style={styles.iconButton} onPress={() => router.back()}>
            <Ionicons name="close" size={22} color={Colors.text} />
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
          <Ionicons name="close" size={22} color={Colors.text} />
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
              <View style={styles.divider} />
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Total final</Text>
                <Text style={styles.totalValue}>${displayTotal.toFixed(2)} MXN</Text>
              </View>
            </View>

            {!openStatus.isOpen ? (
              <View style={styles.minimumWarning}>
                <Ionicons name="moon-outline" size={18} color={Colors.warning} />
                <Text style={styles.minimumWarningText}>
                  La sucursal está cerrada ahora{openStatus.opensAtLabel ? ` — abre a las ${openStatus.opensAtLabel}` : ''}. No puedes continuar con tu pedido hasta que abra.
                </Text>
              </View>
            ) : belowMinimum ? (
              <View style={styles.minimumWarning}>
                <Ionicons name="information-circle-outline" size={18} color={Colors.warning} />
                <Text style={styles.minimumWarningText}>
                  El pedido mínimo es ${pedidoMinimo.toFixed(2)} MXN. Agrega ${missingForMinimum.toFixed(2)} más para continuar.
                </Text>
              </View>
            ) : null}
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
          disabled={checkoutBlocked}
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
            <Ionicons name="document-text-outline" size={12} color={Colors.textMuted} />
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
              <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={14} color={Colors.text} />
            </TouchableOpacity>

            <Text style={styles.qtyNumber}>{item.cantidad}</Text>

            <TouchableOpacity
              activeOpacity={0.6}
              onPress={() => onQty(item.id, item.cantidad + 1)}
              style={styles.qtyAction}
            >
              <Ionicons name="add" size={14} color={Colors.text} />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.surface },
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
    backgroundColor: Colors.borderLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontFamily: FontFamily.heading,
    fontSize: 19,
    color: Colors.text,
  },
  clearText: {
    color: Colors.error,
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
    color: Colors.text,
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
    borderBottomColor: Colors.borderLight,
  },
  itemImg: {
    width: 76,
    height: 76,
    borderRadius: 16,
    backgroundColor: Colors.borderLight,
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
    color: Colors.text,
    flex: 1,
    marginRight: 8,
  },
  itemSubtotal: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.text,
  },
  notesBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: Colors.borderLight,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 6,
    alignSelf: 'flex-start',
    maxWidth: '85%',
  },
  itemNotas: {
    fontSize: 11,
    color: Colors.textMuted,
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
    color: Colors.textMuted,
    fontWeight: '600',
  },
  breakdownValue: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '700',
  },
  extrasList: {
    gap: 3,
  },
  extraText: {
    flex: 1,
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '700',
  },
  extraPrice: {
    fontSize: 12,
    color: Colors.textMuted,
    fontWeight: '800',
  },
  unitTotalRow: {
    marginTop: 2,
    paddingTop: 5,
    borderTopWidth: 1,
    borderTopColor: Colors.borderLight,
  },
  unitTotalLabel: {
    fontSize: 12,
    color: Colors.text,
    fontWeight: '800',
  },
  unitTotalValue: {
    fontSize: 12,
    color: Colors.text,
    fontWeight: '900',
  },
  itemBottomLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemPrecio: { fontSize: 13, color: Colors.textMuted, fontWeight: '500' },

  qtyPillContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.borderLight,
    borderRadius: 20,
    padding: 3,
  },
  qtyAction: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: Colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.sm,
    shadowOpacity: 0.05,
  },
  qtyNumber: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.text,
    minWidth: 28,
    textAlign: 'center',
  },

  summaryContainer: {
    padding: Spacing.base || 16,
    paddingTop: 32,
  },
  summaryBox: {
    backgroundColor: Colors.surfaceElevated,
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: Colors.borderLight,
    gap: 12,
  },
  minimumWarning: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    marginTop: 12,
    padding: 12,
    borderRadius: 14,
    backgroundColor: `${Colors.warning}15`,
    borderWidth: 1,
    borderColor: `${Colors.warning}40`,
  },
  minimumWarningText: {
    flex: 1,
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  summaryLabel: { fontSize: 14, color: Colors.textMuted, fontWeight: '500' },
  summaryValue: { fontSize: 14, fontWeight: '600', color: Colors.text },
  divider: {
    height: 1,
    backgroundColor: Colors.border,
    marginVertical: 4,
    borderStyle: 'dashed',
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  totalLabel: { fontSize: 15, fontWeight: '700', color: Colors.text },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary },

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
    backgroundColor: Colors.surface,
    borderTopWidth: 1,
    borderTopColor: Colors.borderLight,
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
    backgroundColor: Colors.surface,
    borderWidth: 2,
    borderColor: Colors.primary,
  },
});
