import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View, type StyleProp, type ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors, Shadows } from '../../theme';
import type { TableSession } from '../../store/table-session.store';

type TableContextBannerProps = {
  session: TableSession | null;
  branchName?: string | null;
  variant?: 'banner' | 'chip' | 'compact';
  title?: string;
  onPress?: () => void;
  style?: StyleProp<ViewStyle>;
};

export function TableContextBanner({
  session,
  branchName,
  variant = 'banner',
  title = 'Comer aquí',
  onPress,
  style,
}: TableContextBannerProps) {
  if (!session) return null;

  const resolvedBranchName = branchName ?? session.branch?.nombre ?? 'Sucursal seleccionada';
  const containerStyle: StyleProp<ViewStyle> = [
    styles.base,
    variant === 'banner' && styles.banner,
    variant === 'chip' && styles.chip,
    variant === 'compact' && styles.compact,
    style,
  ];

  const content = (
    <>
      <View style={[styles.iconWrap, variant !== 'banner' && styles.iconWrapSmall]}>
        <Ionicons name="restaurant-outline" size={variant === 'banner' ? 18 : 14} color={Colors.primary || '#111827'} />
      </View>

      <View style={styles.copy}>
        <Text
          style={[styles.title, variant !== 'banner' && styles.titleSmall]}
          numberOfLines={1}
        >
          {title} - {session.mesaLabel}
        </Text>
        {variant === 'banner' ? (
          <Text style={styles.subtitle} numberOfLines={1}>
            {resolvedBranchName}
          </Text>
        ) : null}
      </View>

      {variant === 'banner' ? (
        <Ionicons name="checkmark-circle" size={20} color="#16A34A" />
      ) : null}
    </>
  );

  if (onPress) {
    return (
      <TouchableOpacity activeOpacity={0.86} onPress={onPress} style={containerStyle}>
        {content}
      </TouchableOpacity>
    );
  }

  return (
    <View style={containerStyle}>
      {content}
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
  },
  banner: {
    minHeight: 64,
    borderRadius: 18,
    borderColor: '#E8E1D7',
    backgroundColor: '#FFFDF8',
    paddingHorizontal: 14,
    gap: 11,
    ...Shadows.sm,
    shadowOpacity: 0.05,
  },
  chip: {
    minHeight: 38,
    alignSelf: 'flex-start',
    maxWidth: '100%',
    borderRadius: 999,
    borderColor: '#E8DED1',
    backgroundColor: '#FFF9EF',
    paddingHorizontal: 12,
    gap: 7,
  },
  compact: {
    minHeight: 34,
    alignSelf: 'flex-start',
    maxWidth: '100%',
    borderRadius: 12,
    borderColor: '#E5E7EB',
    backgroundColor: '#F9FAFB',
    paddingHorizontal: 10,
    gap: 6,
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 14,
    backgroundColor: '#F3EEE5',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconWrapSmall: {
    width: 26,
    height: 26,
    borderRadius: 10,
  },
  copy: {
    flex: 1,
    minWidth: 0,
  },
  title: {
    fontSize: 15,
    color: '#111827',
    fontWeight: '900',
  },
  titleSmall: {
    fontSize: 13,
  },
  subtitle: {
    marginTop: 2,
    fontSize: 12,
    color: '#6B7280',
    fontWeight: '700',
  },
});
