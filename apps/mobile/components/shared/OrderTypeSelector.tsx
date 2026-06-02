import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing, BorderRadius } from '../../theme';
import type { TipoPedido } from '@amare/types';

const OPTIONS: { tipo: TipoPedido; label: string; icon: keyof typeof Ionicons.glyphMap; desc: string }[] = [
  { tipo: 'pickup',   label: 'Recoger',    icon: 'walk-outline',     desc: 'Recoge en sucursal' },
  { tipo: 'delivery', label: 'Domicilio',  icon: 'bicycle-outline',  desc: 'Entrega a tu dirección' },
  { tipo: 'eat_in',   label: 'En mesa',   icon: 'restaurant-outline', desc: 'Come aquí' },
];

interface OrderTypeSelectorProps {
  value: TipoPedido;
  onChange: (tipo: TipoPedido) => void;
  available?: TipoPedido[];
}

export function OrderTypeSelector({
  value,
  onChange,
  available = ['pickup', 'delivery', 'eat_in'],
}: OrderTypeSelectorProps) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.row}
    >
      {OPTIONS.filter((o) => available.includes(o.tipo)).map((opt) => {
        const active = value === opt.tipo;
        return (
          <TouchableOpacity
            key={opt.tipo}
            onPress={() => onChange(opt.tipo)}
            activeOpacity={0.8}
            style={[styles.chip, active && styles.chipActive]}
          >
            <Ionicons
              name={opt.icon}
              size={18}
              color={active ? Colors.white : Colors.primary}
            />
            <View>
              <Text style={[styles.chipLabel, active && styles.chipLabelActive]}>
                {opt.label}
              </Text>
              <Text style={[styles.chipDesc, active && styles.chipDescActive]}>
                {opt.desc}
              </Text>
            </View>
          </TouchableOpacity>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { gap: Spacing.sm, paddingHorizontal: Spacing.base },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: BorderRadius.lg,
    borderWidth: 1.5,
    borderColor: Colors.border,
    backgroundColor: Colors.surface,
    minWidth: 120,
  },
  chipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  chipLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: Colors.text,
  },
  chipLabelActive: { color: Colors.white },
  chipDesc: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 1,
  },
  chipDescActive: { color: 'rgba(255,255,255,0.7)' },
});
