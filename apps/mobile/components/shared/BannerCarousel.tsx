import React, { useRef, useEffect, useState } from 'react';
import {
  View,
  StyleSheet,
  Dimensions,
  TouchableOpacity,
  Text,
  Animated,
  Platform,
} from 'react-native';
import { Image } from 'expo-image';
import { Colors, Spacing, BorderRadius } from '../../theme';

export interface BannerItem {
  id: string;
  imagen: string;
  titulo?: string;
  subtitulo?: string;
  deepLink?: string;
}

interface BannerCarouselProps {
  items: BannerItem[];
  onPress?: (item: BannerItem) => void;
  autoPlay?: boolean;
  interval?: number;
}

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const BANNER_HEIGHT = 190;
const BANNER_WIDTH = SCREEN_WIDTH - 40; // Ajustado para márgenes laterales de 20

export function BannerCarousel({
  items,
  onPress,
  autoPlay = true,
  interval = 4000,
}: BannerCarouselProps) {
  const flatRef = useRef<Animated.FlatList<BannerItem>>(null);
  const currentIndex = useRef(0);
  const scrollX = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (!autoPlay || items.length <= 1) return;
    const timer = setInterval(() => {
      currentIndex.current = (currentIndex.current + 1) % items.length;
      flatRef.current?.scrollToIndex({
        index: currentIndex.current,
        animated: true,
      });
    }, interval);
    return () => clearInterval(timer);
  }, [autoPlay, interval, items.length]);

  if (items.length === 0) return null;

  return (
    <View>
      <Animated.FlatList
        ref={flatRef}
        data={items}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(item) => item.id}
        snapToInterval={BANNER_WIDTH + 12} // BANNER_WIDTH + gap
        decelerationRate="fast"
        contentContainerStyle={{ paddingHorizontal: 20 }}
        onScroll={Animated.event(
          [{ nativeEvent: { contentOffset: { x: scrollX } } }],
          { useNativeDriver: false }
        )}
        renderItem={({ item }) => (
          <TouchableOpacity
            onPress={() => onPress?.(item)}
            activeOpacity={0.95}
            style={styles.banner}
          >
            <Image
              source={item.imagen}
              style={styles.image}
              contentFit="cover"
              transition={300}
            />
            {(item.titulo || item.subtitulo) && (
              <View style={styles.overlay}>
                {item.titulo && <Text style={styles.titulo}>{item.titulo}</Text>}
                {item.subtitulo && <Text style={styles.subtitulo}>{item.subtitulo}</Text>}
              </View>
            )}
          </TouchableOpacity>
        )}
      />

      {/* Indicadores (Dots) */}
      <View style={styles.pagination}>
        {items.map((_, i) => {
          const inputRange = [
            (i - 1) * (BANNER_WIDTH + 12),
            i * (BANNER_WIDTH + 12),
            (i + 1) * (BANNER_WIDTH + 12),
          ];

          const dotWidth = scrollX.interpolate({
            inputRange,
            outputRange: [6, 16, 6],
            extrapolate: 'clamp',
          });

          const opacity = scrollX.interpolate({
            inputRange,
            outputRange: [0.3, 1, 0.3],
            extrapolate: 'clamp',
          });

          return <Animated.View key={i} style={[styles.dot, { width: dotWidth, opacity }]} />;
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    width: BANNER_WIDTH,
    height: BANNER_HEIGHT,
    borderRadius: BorderRadius.xl,
    overflow: 'hidden',
    marginRight: 12,
  },
  image: { width: '100%', height: '100%' },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-end',
    padding: Spacing.md,
    backgroundColor: 'rgba(0,0,0,0.3)',
  },
  titulo: {
    fontSize: 18,
    fontWeight: '700',
    color: Colors.white,
  },
  subtitulo: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.85)',
    marginTop: 2,
  },
  pagination: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 12,
    gap: 6,
  },
  dot: {
    height: 6,
    borderRadius: 3,
    backgroundColor: Colors.primary,
  },
});
