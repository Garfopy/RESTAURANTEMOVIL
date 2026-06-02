import React, { useRef, useEffect } from 'react';
import {
  FlatList,
  View,
  Image,
  StyleSheet,
  Dimensions,
  TouchableOpacity,
  Text,
} from 'react-native';
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
const BANNER_HEIGHT = 180;
const BANNER_WIDTH = SCREEN_WIDTH - Spacing.base * 2;

export function BannerCarousel({
  items,
  onPress,
  autoPlay = true,
  interval = 4000,
}: BannerCarouselProps) {
  const flatRef = useRef<FlatList<BannerItem>>(null);
  const currentIndex = useRef(0);

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
      <FlatList
        ref={flatRef}
        data={items}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(item) => item.id}
        snapToInterval={BANNER_WIDTH + Spacing.sm}
        decelerationRate="fast"
        contentContainerStyle={{ paddingHorizontal: Spacing.base }}
        renderItem={({ item }) => (
          <TouchableOpacity
            onPress={() => onPress?.(item)}
            activeOpacity={0.95}
            style={styles.banner}
          >
            <Image
              source={{ uri: item.imagen }}
              style={styles.image}
              resizeMode="cover"
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
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    width: BANNER_WIDTH,
    height: BANNER_HEIGHT,
    borderRadius: BorderRadius.xl,
    overflow: 'hidden',
    marginRight: Spacing.sm,
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
});
