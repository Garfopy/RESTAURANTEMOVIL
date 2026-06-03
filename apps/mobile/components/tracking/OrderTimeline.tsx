import React, { useRef, useEffect } from 'react';
import { Animated, View, Text, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing } from '../../theme';
import type { TrackingEvent } from '@amare/types';

interface OrderTimelineProps {
  steps: TrackingEvent[];
}

export function OrderTimeline({ steps }: OrderTimelineProps) {
  return (
    <View style={styles.container}>
      {steps.map((step, index) => (
        <TimelineStep key={step.estado} step={step} index={index} isLast={index === steps.length - 1} />
      ))}
    </View>
  );
}

interface TimelineStepProps {
  step: TrackingEvent;
  index: number;
  isLast: boolean;
}

function TimelineStep({ step, index, isLast }: TimelineStepProps) {
  const opacity = useRef(new Animated.Value(0)).current;
  const translateX = useRef(new Animated.Value(-20)).current;

  useEffect(() => {
    const delay = index * 150;
    Animated.parallel([
      Animated.sequence([Animated.delay(delay), Animated.spring(opacity, { toValue: 1, useNativeDriver: true } as any)]),
      Animated.sequence([Animated.delay(delay), Animated.spring(translateX, { toValue: 0, useNativeDriver: true } as any)]),
    ]).start();
  }, []);

  const animStyle = { opacity, transform: [{ translateX }] };

  const dotColor = step.completado
    ? Colors.success
    : step.en_curso
    ? Colors.accent
    : Colors.border;

  return (
    <Animated.View style={[styles.step, animStyle]}>
      <View style={styles.leftCol}>
        <View style={[styles.dot, { backgroundColor: dotColor }]}>
          {step.completado && <Ionicons name="checkmark" size={10} color={Colors.white} />}
          {step.en_curso && <View style={styles.dotPulse} />}
        </View>
        {!isLast && <View style={[styles.line, { backgroundColor: step.completado ? Colors.success : Colors.border }]} />}
      </View>
      <View style={styles.content}>
        <Text
          style={[
            styles.label,
            step.en_curso && styles.labelActive,
            step.completado && styles.labelDone,
          ]}
        >
          {step.label}
        </Text>
        <Text style={styles.description}>{step.descripcion}</Text>
        {step.timestamp && (
          <Text style={styles.timestamp}>
            {new Date(step.timestamp).toLocaleTimeString('es-MX', {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </Text>
        )}
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: { paddingHorizontal: Spacing.base },
  step: { flexDirection: 'row', gap: Spacing.md, minHeight: 60 },
  leftCol: { alignItems: 'center', width: 24 },
  dot: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dotPulse: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: Colors.white,
  },
  line: { width: 2, flex: 1, marginVertical: 4 },
  content: { flex: 1, paddingBottom: Spacing.md },
  label: { fontSize: 14, fontWeight: '600', color: Colors.textMuted },
  labelActive: { color: Colors.accent },
  labelDone: { color: Colors.text },
  description: { fontSize: 12, color: Colors.textMuted, marginTop: 2 },
  timestamp: { fontSize: 11, color: Colors.textMuted, marginTop: 4 },
});
