import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useStoreProduct } from '../../../hooks/useStore';
import { formatImageUrl } from '../../../services/api';
import { Colors, Spacing, Shadows } from '../../../theme';
import { Skeleton } from '../../../components/ui/Skeleton';

type DeliveryMode = 'pickup' | 'delivery';

export default function StoreProductScreen() {
  const router = useRouter();
  const { id, buyNow } = useLocalSearchParams<{ id: string; buyNow?: string }>();

  const { data: product, isLoading } = useStoreProduct(Number(id));
  const [quantity, setQuantity] = useState(1);
  const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>('pickup');

  if (isLoading || !product) {
    return (
      <SafeAreaView style={styles.safe}>
        <Skeleton height={300} borderRadius={0} />
        <View style={{ padding: Spacing.base, gap: 12 }}>
          <Skeleton height={28} width="60%" />
          <Skeleton height={16} />
          <Skeleton height={16} width="80%" />
          <Skeleton height={48} width="100%" borderRadius={12} />
        </View>
      </SafeAreaView>
    );
  }

  const inStock = product.stock > 0;
  const maxQty = Math.min(product.stock, 20);

  function decreaseQty() {
    setQuantity((prev) => Math.max(1, prev - 1));
  }

  function increaseQty() {
    setQuantity((prev) => Math.min(maxQty, prev + 1));
  }

  function handleBuyNow() {
    if (!product) return;

    if (deliveryMode === 'pickup') {
      // Pickup: va directo a pago sin datos de envío
      router.replace({
        pathname: '/checkout/payment-store' as any,
        params: {
          productId: String(product.id),
          productName: product.nombre,
          productImage: product.imagen ?? '',
          productPrice: String(product.precio),
          quantity: String(quantity),
          tipo_pedido: 'pickup',
          total: String(product.precio * quantity),
        },
      });
    } else {
      // Delivery: va al checkout con datos de envío
      router.replace({
        pathname: '/store/checkout' as any,
        params: {
          productId: String(product.id),
          productName: product.nombre,
          productImage: product.imagen ?? '',
          productPrice: String(product.precio),
          quantity: String(quantity),
          tipo_pedido: 'delivery',
        },
      });
    }
  }

  const total = product.precio * quantity;

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={{ paddingBottom: 100 }} bounces={false}>
        {/* Image */}
        <View style={styles.imageContainer}>
          <Image
            source={
              formatImageUrl(product.imagen) ??
              (require('../../../assets/placeholder-food.jpg') as any)
            }
            style={styles.image}
            contentFit="cover"
            transition={300}
          />

          <TouchableOpacity
            style={styles.backBtn}
            onPress={() => router.back()}
            activeOpacity={0.7}
          >
            <Ionicons name="arrow-back" size={22} color={Colors.text} />
          </TouchableOpacity>

          {/* Stock overlay top-right */}
          <View
            style={[
              styles.imageStockBadge,
              inStock ? styles.imageStockOk : styles.imageStockOut,
            ]}
          >
            <Ionicons
              name={inStock ? 'checkmark-circle' : 'close-circle'}
              size={14}
              color="#FFF"
            />
            <Text style={styles.imageStockText}>
              {inStock ? `${product.stock} en stock` : 'Agotado'}
            </Text>
          </View>
        </View>

        {/* Product info */}
        <View style={styles.info}>
          {product.categoria_nombre && (
            <Text style={styles.category}>{product.categoria_nombre}</Text>
          )}
          <Text style={styles.name}>{product.nombre}</Text>

          {/* Tipo de producto y presentación */}
          {product.tipo_producto === 'comida' && product.presentacion && (
            <View style={styles.comidaBadge}>
              <Ionicons name="restaurant-outline" size={14} color={Colors.accent} />
              <Text style={styles.comidaBadgeText}>Presentación: {product.presentacion}</Text>
            </View>
          )}
          {product.tipo_producto === 'fisico' && (
            <View style={styles.fisicoBadge}>
              <Ionicons name="cube-outline" size={14} color={Colors.primary} />
              <Text style={styles.fisicoBadgeText}>Producto físico</Text>
            </View>
          )}

          {product.descripcion && (
            <Text style={styles.description}>{product.descripcion}</Text>
          )}

          <Text style={styles.price}>${product.precio.toFixed(2)}</Text>

          {/* Delivery mode selector */}
          {inStock && (
            <View style={styles.modeSection}>
              <Text style={styles.qtyLabel}>Modalidad de entrega</Text>
              <View style={styles.modeRow}>
                <TouchableOpacity
                  style={[
                    styles.modeOption,
                    deliveryMode === 'pickup' && styles.modeOptionActive,
                  ]}
                  onPress={() => setDeliveryMode('pickup')}
                  activeOpacity={0.7}
                >
                  <Ionicons
                    name="storefront-outline"
                    size={22}
                    color={deliveryMode === 'pickup' ? '#FFF' : Colors.primary}
                  />
                  <Text
                    style={[
                      styles.modeText,
                      deliveryMode === 'pickup' && styles.modeTextActive,
                    ]}
                  >
                    Recoger en tienda
                  </Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[
                    styles.modeOption,
                    deliveryMode === 'delivery' && styles.modeOptionActive,
                  ]}
                  onPress={() => setDeliveryMode('delivery')}
                  activeOpacity={0.7}
                >
                  <Ionicons
                    name="bicycle-outline"
                    size={22}
                    color={deliveryMode === 'delivery' ? '#FFF' : Colors.primary}
                  />
                  <Text
                    style={[
                      styles.modeText,
                      deliveryMode === 'delivery' && styles.modeTextActive,
                    ]}
                  >
                    Envío a domicilio
                  </Text>
                </TouchableOpacity>
              </View>
            </View>
          )}

          {/* Quantity selector */}
          {inStock && (
            <View style={styles.qtySection}>
              <Text style={styles.qtyLabel}>Cantidad</Text>
              <View style={styles.qtyRow}>
                <TouchableOpacity
                  style={[styles.qtyBtn, quantity <= 1 && styles.qtyBtnDisabled]}
                  onPress={decreaseQty}
                  disabled={quantity <= 1}
                  activeOpacity={0.6}
                >
                  <Ionicons
                    name="remove"
                    size={20}
                    color={quantity <= 1 ? '#D1D5DB' : Colors.primary}
                  />
                </TouchableOpacity>
                <Text style={styles.qtyValue}>{quantity}</Text>
                <TouchableOpacity
                  style={[styles.qtyBtn, quantity >= maxQty && styles.qtyBtnDisabled]}
                  onPress={increaseQty}
                  disabled={quantity >= maxQty}
                  activeOpacity={0.6}
                >
                  <Ionicons
                    name="add"
                    size={20}
                    color={quantity >= maxQty ? '#D1D5DB' : Colors.primary}
                  />
                </TouchableOpacity>
              </View>
            </View>
          )}
        </View>

        {/* How it works */}
        <View style={styles.howItWorks}>
          <Text style={styles.howTitle}>¿Cómo funciona?</Text>
          <View style={styles.steps}>
            <View style={styles.step}>
              <View style={styles.stepCircle}>
                <Ionicons name="cart-outline" size={18} color={Colors.accent} />
              </View>
              <Text style={styles.stepText}>Eliges tu producto</Text>
            </View>
            <View style={styles.stepDivider} />
            <View style={styles.step}>
              <View style={styles.stepCircle}>
                <Ionicons name="card-outline" size={18} color={Colors.accent} />
              </View>
              <Text style={styles.stepText}>Pagas en línea</Text>
            </View>
            <View style={styles.stepDivider} />
            <View style={styles.step}>
              <View style={styles.stepCircle}>
                <Ionicons
                  name={deliveryMode === 'pickup' ? 'storefront-outline' : 'bicycle-outline'}
                  size={18}
                  color={Colors.accent}
                />
              </View>
              <Text style={styles.stepText}>
                {deliveryMode === 'pickup' ? 'Recoges en tienda' : 'Recibes en casa'}
              </Text>
            </View>
          </View>
        </View>

        {/* Info según modalidad */}
        <View style={styles.deliveryInfo}>
          <Ionicons name="time-outline" size={16} color={Colors.muted} />
          <Text style={styles.deliveryInfoText}>
            {deliveryMode === 'pickup'
              ? 'Recoge tu producto en la sucursal después del pago'
              : 'Entrega: 5-15 días hábiles después del pedido'}
          </Text>
        </View>
      </ScrollView>

      {/* Bottom purchase bar */}
      {inStock && (
        <View style={styles.bottomBar}>
          <View style={styles.bottomTotal}>
            <Text style={styles.bottomTotalLabel}>Total</Text>
            <Text style={styles.bottomTotalValue}>${total.toFixed(2)}</Text>
          </View>
          <TouchableOpacity
            style={styles.purchaseBtn}
            onPress={handleBuyNow}
            activeOpacity={0.85}
          >
            <Ionicons
              name={deliveryMode === 'pickup' ? 'storefront-outline' : 'bicycle'}
              size={18}
              color="#FFF"
              style={{ marginRight: 6 }}
            />
            <Text style={styles.purchaseBtnText}>
              {deliveryMode === 'pickup' ? 'Pagar y recoger' : 'Comprar ahora'}
            </Text>
            <Ionicons name="arrow-forward" size={18} color="#FFF" style={{ marginLeft: 4 }} />
          </TouchableOpacity>
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  imageContainer: { width: '100%', height: 320, position: 'relative' },
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
  imageStockBadge: {
    position: 'absolute',
    top: 16,
    right: 16,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
    gap: 4,
  },
  imageStockOk: {
    backgroundColor: Colors.success,
  },
  imageStockOut: {
    backgroundColor: Colors.error,
  },
  imageStockText: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '700',
  },

  // Info
  info: { padding: Spacing.base, gap: Spacing.sm },
  category: {
    fontSize: 12,
    fontWeight: '600',
    color: Colors.accent,
    textTransform: 'uppercase',
    letterSpacing: 1,
  },
  name: {
    fontFamily: 'PlayfairDisplay_700Bold',
    fontSize: 26,
    color: Colors.text,
    lineHeight: 32,
  },
  description: {
    fontSize: 14,
    color: Colors.muted,
    lineHeight: 22,
  },
  price: {
    fontSize: 28,
    fontWeight: '800',
    color: Colors.primary,
    marginTop: 4,
  },

  // Badges de tipo de producto
  comidaBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFF7E6',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    alignSelf: 'flex-start',
    gap: 6,
    borderWidth: 1,
    borderColor: '#F5C060',
  },
  comidaBadgeText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.accent,
  },
  fisicoBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0F4FF',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    alignSelf: 'flex-start',
    gap: 6,
    borderWidth: 1,
    borderColor: '#C4D5F7',
  },
  fisicoBadgeText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },

  // Delivery mode
  modeSection: {
    marginTop: 8,
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  modeRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  modeOption: {
    flex: 1,
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    paddingHorizontal: 8,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
    borderWidth: 2,
    borderColor: '#E5E7EB',
    gap: 8,
  },
  modeOptionActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  modeText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
    textAlign: 'center',
  },
  modeTextActive: {
    color: '#FFF',
  },

  // Quantity
  qtySection: {
    marginTop: 4,
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  qtyLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: Colors.muted,
    marginBottom: 10,
  },
  qtyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  qtyBtn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: Colors.background,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: Colors.border,
  },
  qtyBtnDisabled: {
    opacity: 0.5,
  },
  qtyValue: {
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text,
    minWidth: 32,
    textAlign: 'center',
  },

  // How it works
  howItWorks: {
    marginHorizontal: Spacing.base,
    marginTop: 8,
    backgroundColor: Colors.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  howTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.text,
    marginBottom: 14,
  },
  steps: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  step: {
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  stepCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#FFF7E6',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#F5C060',
  },
  stepText: {
    fontSize: 11,
    fontWeight: '600',
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  stepDivider: {
    width: 20,
    height: 1.5,
    backgroundColor: Colors.border,
    marginBottom: 20,
  },

  // Delivery info
  deliveryInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: Spacing.base,
    gap: 6,
  },
  deliveryInfoText: {
    fontSize: 13,
    color: Colors.muted,
    fontWeight: '500',
  },

  // Bottom bar
  bottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    paddingHorizontal: Spacing.base,
    paddingVertical: 12,
    paddingBottom: 28,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
    ...Shadows.md,
    gap: 12,
  },
  bottomTotal: {
    flex: 1,
  },
  bottomTotalLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: Colors.muted,
  },
  bottomTotalValue: {
    fontSize: 20,
    fontWeight: '800',
    color: Colors.primary,
    marginTop: 2,
  },
  purchaseBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.primary,
    paddingHorizontal: 22,
    paddingVertical: 14,
    borderRadius: 14,
    ...Shadows.md,
  },
  purchaseBtnText: {
    color: '#FFF',
    fontSize: 15,
    fontWeight: '700',
  },
});
