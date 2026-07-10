import React from 'react';
import {
  Alert,
  View,
  Text,
  StyleSheet,
  FlatList,
  Modal,
  TouchableOpacity,
  RefreshControl,
  StatusBar,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import { apiClient, formatImageUrl } from '../../services/api';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import type { BannerItem } from '../../components/shared/BannerCarousel';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

interface Promocion {
  id: string | number;
  titulo: string;
  descripcion?: string;
  imagen?: string;
  deep_link?: string;
  deepLink?: string;
  code?: string | null;
  expires_at?: string | null;
}

export default function PromotionsScreen() {
  const router = useRouter();
  const [selectedPromo, setSelectedPromo] = React.useState<Promocion | null>(null);

  const { data: promos, isLoading, refetch, isFetching } = useQuery<Promocion[]>({
    queryKey: ['promotions'],
    queryFn: async () => {
      const res = await apiClient.get('/promotions');
      return res.data.data ?? [];
    },
  });

  // Filtrar los destacados para el carrusel superior
  const bannerItems = (promos ?? [])
    .map((p): BannerItem | null => {
      const imagen = formatImageUrl(p.imagen);
      if (!imagen) return null;

      return {
        id: String(p.id),
        imagen,
        titulo: p.titulo,
        subtitulo: p.descripcion,
        deepLink: getPromoDeepLink(p),
      };
    })
    .filter((item): item is BannerItem => item !== null);

  const handlePromoPress = (deepLink?: string) => {
    if (!deepLink) return;
    try {
      // Redirección dinámica basada en la API
      router.push(deepLink as any);
    } catch (error) {
      console.warn('Ruta de deepLink inválida o no configurada:', deepLink);
    }
  };

  const copyCode = async (code?: string | null) => {
    if (!code) return;
    await Clipboard.setStringAsync(code);
    Alert.alert('Código copiado', code);
  };

  // Renderizador de esqueleto en lo que carga la petición
  if (isLoading) {
    return <PromotionsSkeleton />;
  }

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="dark-content" backgroundColor={Colors.background} />
      
      <View style={styles.header}>
        <Text style={styles.title}>Promociones</Text>
        <Text style={styles.subtitle}>Aprovecha los beneficios que Amare tiene para ti</Text>
      </View>

      {promos && promos.length > 0 ? (
        <FlatList
          data={promos}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={isFetching}
              onRefresh={refetch}
              tintColor={Colors.primary}
              colors={[Colors.primary]}
            />
          }
          ListHeaderComponent={
            bannerItems.length > 0 ? (
              <View style={styles.carouselContainer}>
                <Text style={styles.sectionTitle}>Destacados de la semana</Text>
                <BannerCarousel items={bannerItems} />
              </View>
            ) : null
          }
          renderItem={({ item }) => {
            const hasImage = !!item.imagen;
            const deepLink = getPromoDeepLink(item);
            return (
              <TouchableOpacity
                style={styles.card}
                onPress={() => setSelectedPromo(item)}
                activeOpacity={0.9}
              >
              {hasImage && (
                <Image
                  source={formatImageUrl(item.imagen)} // <-- Aplicamos el formateador aquí
                  style={styles.cardImage}
                  contentFit="cover"
                  transition={250}
                />
              )}
                
                <View style={styles.cardBody}>
                  <View style={styles.badgeRow}>
                    <View style={styles.promoBadge}>
                      <Text style={styles.promoBadgeText}>Exclusivo</Text>
                    </View>
                    {deepLink && (
                      <Ionicons name="chevron-forward" size={16} color={Colors.textMuted} />
                    )}
                  </View>

                  <Text style={styles.cardTitle} numberOfLines={2}>
                    {item.titulo}
                  </Text>
                  
                  {item.descripcion && (
                    <Text style={styles.cardDesc} numberOfLines={3}>
                      {item.descripcion}
                    </Text>
                  )}
                </View>
              </TouchableOpacity>
            );
          }}
        />
      ) : (
        <View style={styles.emptyWrap}>
          <View style={styles.emptyIcon}>
            <Ionicons name="pricetag-outline" size={34} color={Colors.textMuted} />
          </View>
          <Text style={styles.emptyTitle}>Sin promociones activas</Text>
          <Text style={styles.emptyText}>Vuelve pronto para ver las ofertas del día.</Text>
        </View>
      )}

      <Modal
        visible={selectedPromo !== null}
        transparent
        animationType="fade"
        onRequestClose={() => setSelectedPromo(null)}
      >
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            {selectedPromo?.imagen ? (
              <Image
                source={formatImageUrl(selectedPromo.imagen)}
                style={styles.modalImage}
                contentFit="cover"
              />
            ) : null}
            <View style={styles.modalBody}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>{selectedPromo?.titulo}</Text>
                <TouchableOpacity style={styles.modalClose} onPress={() => setSelectedPromo(null)}>
                  <Ionicons name="close" size={20} color={Colors.text} />
                </TouchableOpacity>
              </View>
              {selectedPromo?.descripcion ? (
                <Text style={styles.modalDesc}>{selectedPromo.descripcion}</Text>
              ) : null}

              {selectedPromo?.code ? (
                <View style={styles.codeBox}>
                  <View>
                    <Text style={styles.codeLabel}>Código</Text>
                    <Text style={styles.codeText}>{selectedPromo.code}</Text>
                  </View>
                  <TouchableOpacity style={styles.copyButton} onPress={() => void copyCode(selectedPromo.code)}>
                    <Ionicons name="copy-outline" size={18} color="#FFFFFF" />
                    <Text style={styles.copyButtonText}>Copiar</Text>
                  </TouchableOpacity>
                </View>
              ) : null}

              {selectedPromo?.expires_at ? (
                <Text style={styles.expiryText}>Vence: {formatPromoDate(selectedPromo.expires_at)}</Text>
              ) : null}

              <View style={styles.modalActions}>
                {getPromoDeepLink(selectedPromo ?? undefined) ? (
                  <TouchableOpacity
                    style={styles.primaryAction}
                    onPress={() => {
                      const deepLink = getPromoDeepLink(selectedPromo ?? undefined);
                      setSelectedPromo(null);
                      handlePromoPress(deepLink);
                    }}
                  >
                    <Text style={styles.primaryActionText}>Ver producto</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

function getPromoDeepLink(promo?: Promocion | null): string | undefined {
  return promo?.deep_link || promo?.deepLink || undefined;
}

function formatPromoDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Vista interna de carga para evitar saltos bruscos en pantalla
function PromotionsSkeleton() {
  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View style={[styles.skeletonLine, { width: '62%', height: 36, marginBottom: 8 }]} />
        <View style={[styles.skeletonLine, { width: '84%', height: 18 }]} />
      </View>
      <View style={{ paddingHorizontal: 24, gap: Spacing.md }}>
        <View style={[styles.skeletonLine, { width: '100%', height: 160, borderRadius: 16 }]} />
        {[1, 2, 3].map((i) => (
          <View key={i} style={[styles.card, { height: 110, backgroundColor: '#EFEFEF', borderWidth: 0 }]} />
        ))}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 16,
    backgroundColor: Colors.background,
  },
  title: { 
    fontSize: 34,
    lineHeight: 42,
    fontWeight: '800', 
    color: Colors.text,
    letterSpacing: -0.8,
    paddingTop: 4,
  },
  subtitle: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.textMuted,
    marginTop: 2,
  },
  listContent: { 
    paddingTop: 8,
    paddingBottom: 120,
  },
  carouselContainer: {
    paddingVertical: Spacing.sm,
    marginBottom: Spacing.md,
  },
  sectionTitle: {
    ...Typography.body,
    fontWeight: '700',
    color: Colors.text,
    marginHorizontal: 24,
    marginBottom: Spacing.sm,
  },
  
  // Refactorización total de Cards (Horizontal Layout con soporte multimedia)
  card: {
    flexDirection: 'row',
    marginHorizontal: 24,
    marginBottom: 16,
    backgroundColor: Colors.surface,
    borderRadius: 20,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: Colors.border || '#F3F4F6',
    ...Shadows.card,
  },
  cardImage: {
    width: 110,
    height: '100%',
    minHeight: 120,
    backgroundColor: '#F3F4F6',
  },
  cardBody: {
    flex: 1,
    padding: Spacing.md,
    justifyContent: 'center',
  },
  badgeRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  promoBadge: {
    backgroundColor: 'rgba(232, 160, 32, 0.12)', // Reemplazar por Colors.primaryLight si existe
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  promoBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.primary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  cardTitle: { 
    fontSize: 15, 
    fontWeight: '700', 
    color: Colors.text,
    lineHeight: 20,
    marginBottom: 4,
  },
  cardDesc: { 
    fontSize: 12, 
    color: Colors.textMuted, 
    lineHeight: 16,
  },
  
  // Auxiliar de Skeletons
  skeletonLine: {
    backgroundColor: '#E5E7EB',
    borderRadius: 4,
  },
  emptyWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 32,
    paddingBottom: 110,
  },
  emptyIcon: {
    width: 84,
    height: 84,
    borderRadius: 42,
    backgroundColor: Colors.borderLight,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 18,
  },
  emptyTitle: {
    fontSize: 20,
    lineHeight: 26,
    fontWeight: '800',
    color: Colors.text,
    textAlign: 'center',
    marginBottom: 8,
  },
  emptyText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.textMuted,
    textAlign: 'center',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.48)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    backgroundColor: Colors.surface,
    borderRadius: 20,
    overflow: 'hidden',
    maxHeight: '86%',
    ...Shadows.card,
  },
  modalImage: {
    width: '100%',
    height: 190,
    backgroundColor: '#F3F4F6',
  },
  modalBody: {
    padding: 18,
    gap: 14,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  modalTitle: {
    flex: 1,
    fontSize: 22,
    lineHeight: 28,
    fontWeight: '900',
    color: Colors.text,
  },
  modalClose: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: Colors.borderLight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalDesc: {
    fontSize: 14,
    lineHeight: 21,
    color: Colors.textMuted,
    fontWeight: '600',
  },
  codeBox: {
    borderRadius: 14,
    backgroundColor: '#FFF7ED',
    borderWidth: 1,
    borderColor: '#FED7AA',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  codeLabel: {
    fontSize: 11,
    fontWeight: '800',
    color: '#9A3412',
    textTransform: 'uppercase',
  },
  codeText: {
    marginTop: 3,
    fontSize: 19,
    lineHeight: 24,
    fontWeight: '900',
    color: '#7C2D12',
  },
  copyButton: {
    minHeight: 42,
    borderRadius: 12,
    paddingHorizontal: 12,
    backgroundColor: Colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  copyButtonText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '900',
  },
  expiryText: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  modalActions: {
    flexDirection: 'row',
  },
  primaryAction: {
    flex: 1,
    minHeight: 48,
    borderRadius: 14,
    backgroundColor: Colors.text,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryActionText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '900',
  },
});
