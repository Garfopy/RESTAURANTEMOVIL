import React, { useEffect, useMemo, useRef } from 'react';
import {
  View,
  StyleSheet,
  TouchableOpacity,
  Text,
  Animated,
  useWindowDimensions,
} from 'react-native';
import { Image } from 'expo-image';
import { Colors, Spacing, BorderRadius } from '../../theme';
import { useThemeColors } from '../../store/theme.store';

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

const BANNER_HEIGHT = 190;
const BANNER_GAP = 12;

export function BannerCarousel({
  items,
  onPress,
  autoPlay = true,
  interval = 4000,
}: BannerCarouselProps) {
  const theme = useThemeColors();
  const { width } = useWindowDimensions();
  const flatRef = useRef<Animated.FlatList<BannerItem>>(null);
  const currentIndex = useRef(0);
  const isInteracting = useRef(false);
  const scrollX = useRef(new Animated.Value(0)).current;
  const horizontalInset = width < 380 ? 16 : 20;
  const bannerWidth = Math.min(Math.max(width - horizontalInset * 2, 260), 430);
  const sidePadding = Math.max((width - bannerWidth) / 2, horizontalInset);
  const snapInterval = bannerWidth + BANNER_GAP;
  const snapOffsets = useMemo(
    () => items.map((_, index) => index * snapInterval),
    [items, snapInterval]
  );

  useEffect(() => {
    if (!autoPlay || items.length <= 1) return;

    const timer = setInterval(() => {
      if (isInteracting.current) return;

      currentIndex.current = (currentIndex.current + 1) % items.length;
      flatRef.current?.scrollToOffset({
        offset: currentIndex.current * snapInterval,
        animated: true,
      });
    }, interval);

    return () => clearInterval(timer);
  }, [autoPlay, interval, items.length, snapInterval]);

  if (items.length === 0) return null;

  return (
    <View>
      <Animated.FlatList
        ref={flatRef}
        data={items}
        horizontal
        showsHorizontalScrollIndicator={false}
        keyExtractor={(item) => item.id}
        snapToInterval={snapInterval}
        snapToOffsets={snapOffsets}
        snapToAlignment="start"
        decelerationRate="fast"
        bounces={false}
        removeClippedSubviews={false}
        contentContainerStyle={{ paddingHorizontal: sidePadding }}
        ItemSeparatorComponent={() => <View style={{ width: BANNER_GAP }} />}
        getItemLayout={(_, index) => ({
          length: snapInterval,
          offset: snapInterval * index,
          index,
        })}
        onScroll={Animated.event(
          [{ nativeEvent: { contentOffset: { x: scrollX } } }],
          { useNativeDriver: false }
        )}
        onScrollBeginDrag={() => {
          isInteracting.current = true;
        }}
        onScrollEndDrag={() => {
          isInteracting.current = false;
        }}
        onMomentumScrollEnd={(event) => {
          const nextIndex = Math.round(event.nativeEvent.contentOffset.x / snapInterval);
          currentIndex.current = Math.max(0, Math.min(nextIndex, items.length - 1));
          isInteracting.current = false;
        }}
        scrollEventThrottle={16}
        renderItem={({ item, index }) => {
          const inputRange = [
            (index - 1) * snapInterval,
            index * snapInterval,
            (index + 1) * snapInterval,
          ];
          const scale = scrollX.interpolate({
            inputRange,
            outputRange: [0.96, 1, 0.96],
            extrapolate: 'clamp',
          });
          const opacity = scrollX.interpolate({
            inputRange,
            outputRange: [0.85, 1, 0.85],
            extrapolate: 'clamp',
          });

          return (
            <Animated.View style={{ width: bannerWidth, transform: [{ scale }], opacity }}>
              <TouchableOpacity
                onPress={() => onPress?.(item)}
                activeOpacity={0.95}
                style={styles.banner}
                accessibilityLabel={item.titulo || 'Promocion'}
                accessibilityRole="button"
                accessibilityHint={item.subtitulo || 'Toca para ver más detalles'}
                testID={`banner-${item.id}`}
              >
                <Image
                  source={item.imagen}
                  style={styles.image}
                  contentFit="cover"
                  transition={180}
                />
                {(item.titulo || item.subtitulo) && (
                  <View style={styles.overlay}>
                    {item.titulo ? <Text style={styles.titulo}>{item.titulo}</Text> : null}
                    {item.subtitulo ? <Text style={styles.subtitulo}>{item.subtitulo}</Text> : null}
                  </View>
                )}
              </TouchableOpacity>
            </Animated.View>
          );
        }}
      />

      <View style={styles.pagination}>
        {items.map((_, i) => {
          const inputRange = [
            (i - 1) * snapInterval,
            i * snapInterval,
            (i + 1) * snapInterval,
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

          return (
            <Animated.View
              key={i}
              style={[styles.dot, { width: dotWidth, opacity, backgroundColor: theme.primary }]}
            />
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    height: BANNER_HEIGHT,
    borderRadius: BorderRadius.xl,
    overflow: 'hidden',
  },
  image: {
    width: '100%',
    height: '100%',
  },
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
