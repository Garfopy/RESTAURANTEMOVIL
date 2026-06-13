import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  Alert,
  Platform,
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
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
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
        contentContainerStyle={styles.list}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => <CartItemRow item={item} onRemove={removeItem} onQty={updateQty} />}
        ListFooterComponent={
          <View style={styles.summaryContainer}>
            <View style={styles.summaryBox}>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Subtotal</Text>
                <Text style={styles.summaryValue}>${total.toFixed(2)}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Costos de gestión</Text>
                <Text style={styles.summaryValue}>$0.00</Text>
              </View>
              <View style={styles.divider} />
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>Total final</Text>
                <Text style={styles.totalValue}>${total.toFixed(2)} MXN</Text>
              </View>
            </View>
          </View>
        }
      />

      <View style={styles.footer}>
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
  return (
    <View style={styles.itemRow}>
      <Image
        source={item.platillo.imagen ?? require('../assets/placeholder-food.jpg')}
        style={styles.itemImg}
        contentFit="cover"
        transition={200}
      />

      <View style={styles.itemInfo}>
        <View style={styles.itemTopLine}>
          <Text style={styles.itemNombre} numberOfLines={1}>
            {item.platillo.nombre}
          </Text>
          <Text style={styles.itemSubtotal}>${item.subtotal.toFixed(2)}</Text>
        </View>

        {item.notas ? (
          <View style={styles.notesBadge}>
            <Ionicons name="document-text-outline" size={12} color="#6B7280" />
            <Text style={styles.itemNotas} numberOfLines={1}>{item.notas}</Text>
          </View>
        ) : null}

        <View style={styles.itemBottomLine}>
          <Text style={styles.itemPrecio}>${item.precio_unitario.toFixed(2)} c/u</Text>

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
  itemInfo: { flex: 1, justifyContent: 'space-between', height: 76 },
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
