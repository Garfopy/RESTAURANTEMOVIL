import React from 'react';
import { View, Text, StyleSheet, FlatList, SafeAreaView } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../../services/api';
import { BannerCarousel } from '../../components/shared/BannerCarousel';
import { EmptyState } from '../../components/ui/EmptyState';
import { Colors, Spacing, Typography } from '../../theme';

interface Promocion {
  id: string;
  titulo: string;
  descripcion?: string;
  imagen?: string;
  deepLink?: string;
}

export default function PromotionsScreen() {
  const { data: promos, isLoading } = useQuery<Promocion[]>({
    queryKey: ['promotions'],
    queryFn: async () => {
      const res = await apiClient.get('/promotions');
      return res.data.data ?? [];
    },
  });

  const bannerItems = (promos ?? []).filter((p) => p.imagen).map((p) => ({
    id: p.id,
    imagen: p.imagen!,
    titulo: p.titulo,
    subtitulo: p.descripcion,
    deepLink: p.deepLink,
  }));

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Text style={styles.title}>Promociones</Text>
      </View>

      {promos && promos.length > 0 ? (
        <FlatList
          data={promos}
          keyExtractor={(p) => p.id}
          contentContainerStyle={{ paddingBottom: 100 }}
          ListHeaderComponent={
            bannerItems.length > 0 ? (
              <View style={{ paddingVertical: Spacing.base }}>
                <BannerCarousel items={bannerItems} />
              </View>
            ) : null
          }
          renderItem={({ item }) => (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>{item.titulo}</Text>
              {item.descripcion && (
                <Text style={styles.cardDesc}>{item.descripcion}</Text>
              )}
            </View>
          )}
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

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  title: { ...Typography.h2, fontWeight: '700', color: Colors.text },
  card: {
    marginHorizontal: Spacing.base,
    marginBottom: Spacing.sm,
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: Spacing.md,
    gap: 4,
  },
  cardTitle: { fontSize: 15, fontWeight: '700', color: Colors.text },
  cardDesc: { fontSize: 13, color: Colors.textMuted, lineHeight: 19 },
});
