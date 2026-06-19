import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  FlatList,
  TouchableOpacity,
  StatusBar,
  Dimensions,
  TextInput,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useStoreCategories, useStoreProducts } from '../../hooks/useStore';
import { formatImageUrl } from '../../services/api';
import { Colors, Shadows } from '../../theme';
import { Skeleton } from '../../components/ui/Skeleton';
import type { StoreCategory, StoreProduct } from '@amare/types';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const CARD_GAP = 14; // Un toque más de separación entre tarjetas
const NUM_COLUMNS = 2;
const CARD_WIDTH = (SCREEN_WIDTH - 16 * 2 - CARD_GAP * (NUM_COLUMNS - 1)) / NUM_COLUMNS;

export default function StoreScreen() {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | undefined>(undefined);

  const { data: categories, isLoading: loadingCats } = useStoreCategories();
  const { data: products, isLoading: loadingProducts } = useStoreProducts(
    search.trim() || selectedCategoryId
      ? { categoria_id: selectedCategoryId, q: search.trim() || undefined }
      : undefined
  );

  function handleCategoryPress(cat: StoreCategory) {
    if (selectedCategoryId === cat.id) {
      setSelectedCategoryId(undefined);
    } else {
      setSelectedCategoryId(cat.id);
    }
  }

  function handleProductPress(product: StoreProduct) {
    router.replace({
      pathname: '/store/product/[id]',
      params: { id: String(product.id) },
    });
  }

  function handleBuyPress(product: StoreProduct) {
    router.replace({
      pathname: '/store/product/[id]',
      params: { id: String(product.id), buyNow: '1' },
    });
  }

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="dark-content" backgroundColor="#FAFAFA" />

      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backBtn}
          onPress={() => router.back()}
          activeOpacity={0.7}
        >
          <Ionicons name="arrow-back" size={22} color={Colors.primary} />
        </TouchableOpacity>

        <View style={styles.headerCenter}>
          <Ionicons name="storefront-outline" size={20} color={Colors.primary} style={{ marginRight: 8 }} />
          <Text style={styles.headerTitle}>Tienda</Text>
        </View>

        <View style={styles.backBtn} />
      </View>

      {/* Hero Banner */}
      <View style={styles.heroBanner}>
        <View style={styles.heroContent}>
          <View style={styles.heroIconContainer}>
            <Ionicons name="rocket-outline" size={22} color={Colors.accent} />
          </View>
          <View style={styles.heroTextBlock}>
            <Text style={styles.heroTitle}>Todo lo que ves aquí puede ser tuyo</Text>
            <Text style={styles.heroSubtitle}>
              Productos exclusivos · Envío directo a tu puerta
            </Text>
          </View>
        </View>
        <View style={styles.heroDeliveryBadge}>
          <Ionicons name="bicycle" size={14} color="#FFF" />
          <Text style={styles.heroDeliveryText}>Solo a domicilio</Text>
        </View>
      </View>

      {/* Search */}
      <View style={styles.searchContainer}>
        <View style={styles.searchBar}>
          <Ionicons name="search-outline" size={18} color="#9CA3AF" />
          <TextInput
            style={styles.searchInput}
            placeholder="Buscar productos de tienda..."
            placeholderTextColor="#9CA3AF"
            value={search}
            onChangeText={setSearch}
            returnKeyType="search"
          />
          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Ionicons name="close-circle" size={18} color="#9CA3AF" />
            </TouchableOpacity>
          )}
        </View>
      </View>

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
      >
        {/* Categories horizontal */}
        {loadingCats ? (
          <View style={styles.catRow}>
            {[1, 2, 3, 4].map((i) => (
              <Skeleton key={i} width={100} height={36} borderRadius={18} style={{ marginRight: 8 }} />
            ))}
          </View>
        ) : (
          <FlatList
            horizontal
            data={categories}
            keyExtractor={(item) => item.id.toString()}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.catRow}
            renderItem={({ item }) => {
              const isActive = selectedCategoryId === item.id;
              return (
                <TouchableOpacity
                  style={[styles.catChip, isActive && styles.catChipActive]}
                  onPress={() => handleCategoryPress(item)}
                  activeOpacity={0.7}
                >
                  {item.imagen ? (
                    <Image
                      source={{ uri: item.imagen }}
                      style={styles.catIcon}
                      contentFit="cover"
                    />
                  ) : (
                    <Ionicons
                      name="pricetag-outline"
                      size={14}
                      color={isActive ? '#FFF' : Colors.accent}
                    />
                  )}
                  <Text style={[styles.catText, isActive && styles.catTextActive]}>
                    {item.nombre}
                  </Text>
                </TouchableOpacity>
              );
            }}
          />
        )}

        {/* Section header */}
        <View style={styles.sectionHeader}>
          <View style={styles.sectionTitleRow}>
            <Ionicons
              name={selectedCategoryId ? 'funnel-outline' : 'apps-outline'}
              size={18}
              color={Colors.accent}
              style={{ marginRight: 6 }}
            />
            <Text style={styles.sectionTitle}>
              {selectedCategoryId
                ? categories?.find((c) => c.id === selectedCategoryId)?.nombre ?? 'Productos'
                : 'Todos los productos'}
            </Text>
          </View>
          <View style={styles.sectionCountBadge}>
            <Text style={styles.sectionCountText}>{products?.length ?? 0}</Text>
          </View>
        </View>

        {/* Products grid */}
        {loadingProducts ? (
          <View style={styles.grid}>
            {[1, 2, 3, 4].map((i) => (
              <Skeleton
                key={i}
                width={CARD_WIDTH}
                height={260}
                borderRadius={16}
                style={{ marginBottom: CARD_GAP }}
              />
            ))}
          </View>
        ) : (products?.length ?? 0) === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="cube-outline" size={56} color="#D1D5DB" />
            <Text style={styles.emptyText}>No se encontraron productos</Text>
            <Text style={styles.emptySubtext}>
              Intenta con otra búsqueda o categoría
            </Text>
          </View>
        ) : (
          <View style={styles.grid}>
            {products!.map((product) => {
              const inStock = product.stock > 0;
              return (
                <TouchableOpacity
                  key={product.id}
                  style={[styles.card, !inStock && styles.cardDisabled]}
                  onPress={() => handleProductPress(product)}
                  activeOpacity={0.95}
                >
                  {/* Image Wrapper */}
                  <View style={styles.cardImageWrapper}>
                    <Image
                      source={
                        formatImageUrl(product.imagen) ??
                        (require('../../assets/placeholder-food.jpg') as any)
                      }
                      style={styles.cardImage}
                      contentFit="cover"
                      transition={200}
                    />

                    {/* Stock badge */}
                    <View
                      style={[
                        styles.stockBadge,
                        inStock ? styles.stockBadgeOk : styles.stockBadgeOut,
                      ]}
                    >
                      <Ionicons
                        name={inStock ? 'checkmark' : 'close'}
                        size={10}
                        color={inStock ? Colors.success : '#FFF'}
                      />
                      <Text style={[styles.stockBadgeText, !inStock && styles.textWhite]}>
                        {inStock ? `${product.stock} disp.` : 'Agotado'}
                      </Text>
                    </View>

                    {/* Sold out overlay */}
                    {!inStock && (
                      <View style={styles.soldOutOverlay}>
                        <Ionicons name="lock-closed" size={24} color="#FFF" />
                        <Text style={styles.soldOutText}>Agotado</Text>
                      </View>
                    )}
                  </View>

                   {/* Info */}
                   <View style={styles.cardInfo}>
                     <View style={styles.cardMainContent}>
                       {product.categoria_nombre && (
                         <Text style={styles.cardCategory} numberOfLines={1}>
                           {product.categoria_nombre}
                         </Text>
                       )}
                       <Text style={styles.cardName} numberOfLines={2}>
                         {product.nombre}
                       </Text>
                       {product.tipo_producto === 'comida' && product.presentacion && (
                         <View style={styles.presentacionBadge}>
                           <Ionicons name="restaurant-outline" size={10} color={Colors.accent} />
                           <Text style={styles.presentacionText}>{product.presentacion}</Text>
                         </View>
                       )}
                     </View>

                    {/* Bottom row rearranged */}
                    <View style={styles.cardBottom}>
                      <Text style={styles.cardPrice}>${product.precio.toFixed(2)}</Text>
                      {inStock && (
                        <TouchableOpacity
                          style={styles.buyBtn}
                          onPress={() => handleBuyPress(product)}
                          activeOpacity={0.7}
                        >
                          <Ionicons name="cart-outline" size={16} color="#FFF" />
                        </TouchableOpacity>
                      )}
                    </View>
                  </View>
                </TouchableOpacity>
              );
            })}
          </View>
        )}

        {/* Footer message */}
        <View style={styles.footerMessage}>
          <Ionicons name="heart-outline" size={16} color={Colors.muted} />
          <Text style={styles.footerText}>
            Productos exclusivos de Amare · Hechos con amor
          </Text>
        </View>

        <View style={{ height: 40 }} />
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#FAFAFA' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#FFF',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerCenter: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },

  // Hero Banner
  heroBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    backgroundColor: Colors.primary,
    borderRadius: 18,
    padding: 16,
    overflow: 'hidden',
    ...Shadows.md,
  },
  heroContent: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  heroIconContainer: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(232, 160, 32, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
  },
  heroTextBlock: {
    flex: 1,
  },
  heroTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: '#FFFFFF',
    lineHeight: 22,
  },
  heroSubtitle: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.75)',
    marginTop: 4,
    lineHeight: 18,
  },
  heroDeliveryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(232, 160, 32, 0.2)',
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 5,
    marginTop: 10,
    gap: 6,
    borderWidth: 1,
    borderColor: 'rgba(232, 160, 32, 0.3)',
  },
  heroDeliveryText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#FFF',
    letterSpacing: 0.3,
  },

  // Search
  searchContainer: {
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFF',
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 10,
    gap: 8,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    ...Shadows.sm,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: '#111827',
    padding: 0,
  },
  scrollContent: {
    paddingBottom: 100,
  },
  catRow: {
    paddingHorizontal: 16,
    paddingBottom: 14,
    gap: 8,
  },
  catChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 22,
    backgroundColor: '#FFF',
    marginRight: 8,
    gap: 6,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    ...Shadows.sm,
  },
  catChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  catIcon: {
    width: 18,
    height: 18,
    borderRadius: 9,
  },
  catText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#6B7280',
  },
  catTextActive: {
    color: '#FFFFFF',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 14,
    marginTop: 4,
  },
  sectionTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: '#111827',
  },
  sectionCountBadge: {
    backgroundColor: Colors.accentLight,
    borderRadius: 12,
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sectionCountText: {
    fontSize: 12,
    fontWeight: '800',
    color: Colors.primary,
  },

  // Grid & Cards Refactored
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: 16,
    gap: CARD_GAP,
  },
  card: {
    width: CARD_WIDTH,
    backgroundColor: '#FFF',
    borderRadius: 16,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#F3F4F6',
    ...Shadows.card,
  },
  cardDisabled: {
    opacity: 0.7,
  },
  cardImageWrapper: {
    position: 'relative',
  },
  cardImage: {
    width: '100%',
    height: 135, // Reducido un pícaro píxel para balancear proporciones
  },
  stockBadge: {
    position: 'absolute',
    top: 8,
    left: 8,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 3,
  },
  stockBadgeOk: {
    backgroundColor: '#E6F4EA', // Fondo claro sutil para un look más moderno
  },
  stockBadgeOut: {
    backgroundColor: Colors.error,
  },
  stockBadgeText: {
    color: Colors.success,
    fontSize: 10,
    fontWeight: '700',
  },
  textWhite: {
    color: '#FFF',
  },
  soldOutOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  soldOutText: {
    color: '#FFF',
    fontSize: 13,
    fontWeight: '700',
  },
  cardInfo: {
    padding: 12, // Más padding interno para dar aire
    flex: 1,
    justifyContent: 'space-between',
    minHeight: 110, // Mantiene la consistencia de altura si un título es corto
  },
  cardMainContent: {
    marginBottom: 10,
  },
  cardCategory: {
    fontSize: 10,
    fontWeight: '600',
    color: Colors.accent,
    textTransform: 'uppercase',
    marginBottom: 4,
    letterSpacing: 0.5,
  },
  cardName: {
    fontSize: 13,
    fontWeight: '600', // Peso intermedio para que no abrume visualmente
    color: '#1F2937',
    lineHeight: 18,
  },
  presentacionBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFF7E6',
    borderRadius: 6,
    paddingHorizontal: 6,
    paddingVertical: 3,
    marginTop: 6,
    alignSelf: 'flex-start',
    gap: 3,
    borderWidth: 1,
    borderColor: '#F5C060',
  },
  presentacionText: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.accent,
  },
  cardBottom: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 'auto', // Empuja el precio/botón siempre al fondo
  },
  cardPrice: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.primary,
  },
  buyBtn: {
    width: 32,
    height: 32,
    borderRadius: 16, // Botón circular flotante súper limpio
    backgroundColor: Colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },

  // Empty state
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    gap: 10,
  },
  emptyText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#9CA3AF',
  },
  emptySubtext: {
    fontSize: 13,
    color: '#D1D5DB',
    textAlign: 'center',
    paddingHorizontal: 40,
  },

  // Footer
  footerMessage: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 32,
    gap: 6,
  },
  footerText: {
    fontSize: 12,
    color: Colors.muted,
    fontWeight: '500',
  },
});
