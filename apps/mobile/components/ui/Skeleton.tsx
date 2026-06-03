import React, { useEffect, useRef } from 'react';
import { Animated, View, StyleSheet, ViewStyle } from 'react-native';
import { Colors, BorderRadius } from '../../theme';

interface SkeletonProps {
  width?: number | `${number}%`;
  height?: number;
  borderRadius?: number;
  style?: ViewStyle;
}

export function Skeleton({ width = '100%', height = 16, borderRadius = BorderRadius.sm, style }: SkeletonProps) {
  const shimmer = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(shimmer, { toValue: 1, duration: 1200, useNativeDriver: false }),
        Animated.timing(shimmer, { toValue: 0, duration: 1200, useNativeDriver: false }),
      ])
    ).start();
  }, [shimmer]);

  const backgroundColor = shimmer.interpolate({
    inputRange: [0, 1],
    outputRange: ['#E5E5EA', '#F2F2F7'],
  });

  return (
    <Animated.View
      style={[{ width, height, borderRadius } as ViewStyle, { backgroundColor }, style]}
    />
  );
}

export function SkeletonCard() {
  return (
    <View style={styles.card}>
      <Skeleton height={160} borderRadius={12} />
      <Skeleton width="70%" height={16} style={{ marginTop: 10 }} />
      <Skeleton width="40%" height={14} style={{ marginTop: 6 }} />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
});
