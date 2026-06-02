import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  Alert,
} from 'react-native';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useCartStore } from '../store/cart.store';
import { Button } from '../components/ui/Button';
import { EmptyState } from '../components/ui/EmptyState';
import { OrderTypeSelector } from '../components/shared/OrderTypeSelector';
import { Colors, Spacing, Typography, Shadows } from '../theme';
import type { CarritoItem } from '@amare/types';

export default function CartScreen() {
  const router = useRouter();
  const { items, removeItem, updateQty, total, clear, tipoPedido, setTipoPedido } = useCartStore();

  function handleCheckout() {
    router.push('/checkout/order-type');
  }

  if (items.length === 0) {
    return (
      <SafeAreaView style={styles.safe}>
        <View style={styles.header}>
          <TouchableOpacity onPress={() => router.back()}>
            <Ionicons name="close" size={26} color={Colors.text} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Tu pedido</Text>
          <View style={{ width: 26 }} />
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
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()}>
          <Ionicons name="close" size={26} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Tu pedido</Text>
        <TouchableOpacity onPress={() => Alert.alert('Vaciar carrito', '¿Seguro?', [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Vaciar', style: 'destructive', onPress: () => { clear(); router.back(); } },
        ])}>
          <Text style={styles.clearText}>Vaciar</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={items}
        keyExtractor={(i) => i.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.typeRow}>
            <Text style={styles.sectionLabel}>Tipo de pedido</Text>
            <OrderTypeSelector value={tipoPedido} onChange={setTipoPedido} />
          </View>
        }
        renderItem={({ item }) => <CartItemRow item={item} onRemove={removeItem} onQty={updateQty} />}
        ListFooterComponent={
          <View style={styles.summary}>
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Subtotal</Text>
              <Text style={styles.summaryValue}>${total.toFixed(2)}</Text>
            </View>
            <View style={[styles.summaryRow, styles.summaryTotal]}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>${total.toFixed(2)}</Text>
            </View>
          </View>
        }
      />

      <View style={styles.footer}>
        <Button
          label={`Continuar · $${total.toFixed(2)}`}
          onPress={handleCheckout}
          fullWidth
          size="lg"
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
  return (
    <View style={styles.itemRow}>
      <Image
            source={item.platillo.imagen ?? require('../assets/placeholder-food.png')}
        style={styles.itemImg}
        contentFit="cover"
      />
      <View style={styles.itemInfo}>
        <Text style={styles.itemNombre} numberOfLines={2}>
          {item.platillo.nombre}
        </Text>
        {item.notas ? <Text style={styles.itemNotas}>{item.notas}</Text> : null}
        <Text style={styles.itemPrecio}>${item.precio_unitario.toFixed(2)} c/u</Text>
        <View style={styles.qtyRow}>
          <TouchableOpacity
            onPress={() => item.cantidad > 1 ? onQty(item.id, item.cantidad - 1) : onRemove(item.id)}
            style={styles.qtyBtn}
          >
            <Ionicons name={item.cantidad > 1 ? 'remove' : 'trash-outline'} size={14} color={Colors.text} />
          </TouchableOpacity>
          <Text style={styles.qty}>{item.cantidad}</Text>
          <TouchableOpacity onPress={() => onQty(item.id, item.cantidad + 1)} style={styles.qtyBtn}>
            <Ionicons name="add" size={14} color={Colors.text} />
          </TouchableOpacity>
        </View>
      </View>
      <Text style={styles.itemSubtotal}>${item.subtotal.toFixed(2)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h3, fontWeight: '700', color: Colors.text },
  clearText: { color: Colors.error, fontSize: 14, fontWeight: '600' },
  list: { paddingBottom: 160 },
  typeRow: {
    paddingHorizontal: Spacing.base,
    paddingTop: Spacing.base,
    paddingBottom: Spacing.sm,
    gap: 8,
  },
  sectionLabel: { fontSize: 13, fontWeight: '600', color: Colors.textMuted, paddingLeft: Spacing.base },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  itemImg: { width: 64, height: 64, borderRadius: 10 },
  itemInfo: { flex: 1, gap: 4 },
  itemNombre: { fontSize: 14, fontWeight: '600', color: Colors.text, lineHeight: 18 },
  itemNotas: { fontSize: 12, color: Colors.textMuted, fontStyle: 'italic' },
  itemPrecio: { fontSize: 12, color: Colors.textMuted },
  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  qtyBtn: {
    width: 28,
    height: 28,
    borderRadius: 8,
    backgroundColor: Colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: Colors.border,
  },
  qty: { fontSize: 14, fontWeight: '700', color: Colors.text, minWidth: 20, textAlign: 'center' },
  itemSubtotal: { fontSize: 15, fontWeight: '700', color: Colors.primary },
  summary: {
    margin: Spacing.base,
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: Spacing.md,
    gap: 8,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between' },
  summaryLabel: { fontSize: 14, color: Colors.textMuted },
  summaryValue: { fontSize: 14, fontWeight: '600', color: Colors.text },
  summaryTotal: { marginTop: 4, paddingTop: 8, borderTopWidth: 1, borderTopColor: Colors.border },
  totalLabel: { fontSize: 16, fontWeight: '700', color: Colors.text },
  totalValue: { fontSize: 18, fontWeight: '800', color: Colors.primary },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: Spacing.base,
    paddingBottom: 28,
    backgroundColor: Colors.background,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
    ...Shadows.md,
  },
});
