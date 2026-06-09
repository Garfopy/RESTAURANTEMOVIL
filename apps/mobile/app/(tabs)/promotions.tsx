import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  SafeAreaView,
  TouchableOpacity,
  RefreshControl,
  StatusBar,
} from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { apiClient, formatImageUrl } from '../../services/api';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { EmptyState } from '../../components/ui/EmptyState';
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
    .filter((p) => p.imagen)
    .map((p) => ({
      id: p.id,
      imagen: formatImageUrl(p.imagen!),
      titulo: p.titulo,
      subtitulo: p.descripcion,
      deepLink: p.deepLink,
    }));

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
      
      {/* Header Estilizado */}
      <View style={styles.header}>
        <View style={styles.headerTitleRow}>
          <Ionicons name="gift-outline" size={24} color={Colors.primary} style={{ marginRight: 8 }} />
          <Text style={styles.title}>Promociones</Text>
        </View>
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
        <EmptyState
          icon="pricetag-outline"
          title="Sin promociones activas"
          description="Vuelve pronto para ver las ofertas del día."
        />
      )}
    </SafeAreaView>
  );
}

// Vista interna de carga para evitar saltos bruscos en pantalla
function PromotionsSkeleton() {
  return (
    <SafeAreaView style={styles.safe}>
      <View style={[styles.header, { borderBottomWidth: 0 }]}>
        <View style={[styles.skeletonLine, { width: '50%', height: 28, marginBottom: 8 }]} />
        <View style={[styles.skeletonLine, { width: '80%', height: 16 }]} />
      </View>
      <View style={{ paddingHorizontal: Spacing.base, gap: Spacing.md }}>
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
    paddingHorizontal: Spacing.base,
    paddingTop: Spacing.md,
    paddingBottom: Spacing.base,
    backgroundColor: Colors.background,
  },
  headerTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  title: { 
    ...Typography.h2, 
    fontWeight: '800', 
    color: Colors.text,
    letterSpacing: -0.5,
  },
  subtitle: {
    ...Typography.bodySmall,
    color: Colors.textMuted,
  },
  listContent: { 
    paddingBottom: 120,
  },
  carouselContainer: {
    paddingVertical: Spacing.sm,
    marginBottom: Spacing.md,
  },
  sectionTitle: {
    ...Typography.bodyMedium,
    fontWeight: '700',
    color: Colors.text,
    marginHorizontal: Spacing.base,
    marginBottom: Spacing.sm,
  },
  
  // Refactorización total de Cards (Horizontal Layout con soporte multimedia)
  card: {
    flexDirection: 'row',
    marginHorizontal: Spacing.base,
    marginBottom: Spacing.base,
    backgroundColor: Colors.surface,
    borderRadius: 16,
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
});