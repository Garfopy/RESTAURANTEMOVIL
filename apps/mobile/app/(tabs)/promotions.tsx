import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  StatusBar,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { apiClient, formatImageUrl } from '../../services/api';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import type { BannerItem } from '../../components/shared/BannerCarousel';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

interface Promocion {
  id: string;
  titulo: string;
  descripcion?: string;
  imagen?: string;
  deepLink?: string;
}

export default function PromotionsScreen() {
  const router = useRouter();

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
        id: p.id,
        imagen,
        titulo: p.titulo,
        subtitulo: p.descripcion,
        deepLink: p.deepLink,
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
          keyExtractor={(p) => p.id}
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
            return (
              <TouchableOpacity
                style={styles.card}
                onPress={() => handlePromoPress(item.deepLink)}
                activeOpacity={item.deepLink ? 0.9 : 1}
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
                    {item.deepLink && (
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
          <Text style={styles.emptyText}>Vuelve pronto para ver las ofertas del dia.</Text>
        </View>
      )}
    </SafeAreaView>
  );
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
});
