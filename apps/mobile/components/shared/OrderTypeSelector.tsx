import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Spacing, BorderRadius } from '../../theme';
import { useThemeColors } from '../../store/theme.store';
import type { TipoPedido } from '@amare/types';

const OPTIONS: { tipo: TipoPedido; label: string; icon: keyof typeof Ionicons.glyphMap; desc: string }[] = [
  { tipo: 'pickup',   label: 'Recoger',    icon: 'walk-outline',     desc: 'Recoge en sucursal' },
  { tipo: 'delivery', label: 'Domicilio',  icon: 'bicycle-outline',  desc: 'Entrega a tu dirección' },
];

interface OrderTypeSelectorProps {
  value: TipoPedido | null;
  onChange: (tipo: TipoPedido) => void;
  available?: TipoPedido[];
}

export function OrderTypeSelector({
  value,
  onChange,
  available = ['pickup', 'delivery'],
}: OrderTypeSelectorProps) {
  const theme = useThemeColors();
  const visibleOptions = OPTIONS.filter((o) => available.includes(o.tipo));

  return (
    <View style={styles.row}>
      {visibleOptions.map((opt, index) => {
        const active = value === opt.tipo;
        const isHalfWidth = visibleOptions.length <= 2 || (visibleOptions.length === 3 && index < 2);

        return (
          <TouchableOpacity
            key={opt.tipo}
            onPress={() => onChange(opt.tipo)}
            activeOpacity={0.8}
            style={[
              styles.chip,
              isHalfWidth ? styles.chipHalf : styles.chipFull,
              active && { backgroundColor: theme.primary, borderColor: theme.primary },
            ]}
            accessibilityLabel={`Seleccionar ${opt.label.toLowerCase()}`}
            accessibilityRole="radio"
            accessibilityState={{ selected: active }}
            accessibilityHint={opt.desc}
            testID={`order-type-${opt.tipo}`}
          >
            <Ionicons
              name={opt.icon}
              size={18}
              color={active ? Colors.white : theme.primary}
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
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: Spacing.sm,
    width: '100%',
  },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: BorderRadius.lg,
    borderWidth: 1.5,
    borderColor: Colors.border,
    backgroundColor: Colors.surface,
    minHeight: 72,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.04,
    shadowRadius: 10,
    elevation: 2,
  },
  chipHalf: {
    flexBasis: '48%',
    flexGrow: 1,
  },
  chipFull: {
    flexBasis: '100%',
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
