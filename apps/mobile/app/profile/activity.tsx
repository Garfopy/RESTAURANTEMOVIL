import React, { useMemo, useState } from 'react';
import {
  ActivityIndicator,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { getRewardsWallet, type RewardTransaction, type RewardsWallet } from '../../services/rewards.service';
import { Colors, Shadows, FontFamily } from '../../theme';

type TransactionFilter = 'all' | 'topup' | 'spend' | 'points';

const FILTERS: Array<{ key: TransactionFilter; label: string }> = [
  { key: 'all', label: 'Todos' },
  { key: 'topup', label: 'Recargas' },
  { key: 'spend', label: 'Gastos' },
  { key: 'points', label: 'Puntos' },
];

function parseTransactionDate(value: string): Date | null {
  const normalized = value ? value.replace(' ', 'T') : '';
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
}

function startOfDay(date: Date): number {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
}

function formatGroupDate(value: string): string {
  const date = parseTransactionDate(value);
  if (!date) return 'Fecha pendiente';

  const today = startOfDay(new Date());
  const txDay = startOfDay(date);
  const oneDay = 24 * 60 * 60 * 1000;

  if (txDay === today) return 'Hoy';
  if (txDay === today - oneDay) return 'Ayer';

  return new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

function formatTransactionTime(value: string): string {
  const date = parseTransactionDate(value);
  if (!date) return value;

  return new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function getTransactionKind(tx: RewardTransaction): Exclude<TransactionFilter, 'all'> {
  const amount = Number(tx.amount_mxn ?? 0);
  const points = Number(tx.points_delta ?? 0);

  if (amount < 0) return 'spend';
  if (amount > 0) return 'topup';
  if (points !== 0) return 'points';

  return 'points';
}

function getTransactionTitle(tx: RewardTransaction): string {
  const description = (tx.description || '').trim();
  const lower = description.toLowerCase();

  if (lower.includes('recarga')) return 'Recarga con Stripe';
  if (lower.includes('regalo social')) return 'Regalo social';
  if (lower.includes('compra de alimentos')) return 'Compra de alimentos';

  return description || 'Movimiento';
}

function getTransactionIcon(kind: Exclude<TransactionFilter, 'all'>): keyof typeof Ionicons.glyphMap {
  if (kind === 'topup') return 'arrow-down';
  if (kind === 'spend') return 'arrow-up';
  return 'sparkles';
}

function getKindStyles(kind: Exclude<TransactionFilter, 'all'>) {
  if (kind === 'topup') {
    return {
      iconWrap: styles.iconWrapTopup,
      iconColor: Colors.success,
      amount: styles.positive,
      badge: styles.pointsBadgeNeutral,
    };
  }

  if (kind === 'spend') {
    return {
      iconWrap: styles.iconWrapSpend,
      iconColor: Colors.error,
      amount: styles.negative,
      badge: styles.pointsBadgeNeutral,
    };
  }

  return {
    iconWrap: styles.iconWrapPoints,
    iconColor: Colors.accentDark,
    amount: styles.neutralAmount,
    badge: styles.pointsBadgeActive,
  };
}

export default function ProfileActivityScreen() {
  const router = useRouter();
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeFilter, setActiveFilter] = useState<TransactionFilter>('all');

  useFocusEffect(
    React.useCallback(() => {
      void loadActivity();
    }, [])
  );

  async function loadActivity() {
    setLoading(true);
    try {
      setWallet(await getRewardsWallet());
    } catch (error) {
      console.warn('No se pudo cargar la actividad reciente', error);
    } finally {
      setLoading(false);
    }
  }

  const filteredTransactions = useMemo(() => {
    const transactions = wallet?.transactions ?? [];
    if (activeFilter === 'all') return transactions;

    return transactions.filter((tx) => getTransactionKind(tx) === activeFilter);
  }, [activeFilter, wallet?.transactions]);

  const groupedTransactions = useMemo(() => {
    return filteredTransactions.reduce<Array<{ title: string; data: RewardTransaction[] }>>((groups, tx) => {
      const title = formatGroupDate(tx.created_at);
      const lastGroup = groups[groups.length - 1];

      if (lastGroup?.title === title) {
        lastGroup.data.push(tx);
      } else {
        groups.push({ title, data: [tx] });
      }

      return groups;
    }, []);
  }, [filteredTransactions]);

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={() => router.back()} accessibilityRole="button">
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Actividad reciente</Text>
        <TouchableOpacity style={styles.refreshButton} onPress={loadActivity} accessibilityRole="button">
          <Ionicons name="refresh" size={19} color={Colors.textSecondary} />
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.loadingWrap}>
          <ActivityIndicator size="large" color={Colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.content}>
          <View style={styles.summaryBand}>
            <View>
              <Text style={styles.summaryLabel}>Saldo</Text>
              <Text style={styles.summaryValue}>${Number(wallet?.balance_mxn ?? 0).toFixed(2)}</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryRight}>
              <Text style={styles.summaryLabel}>Puntos</Text>
              <Text style={styles.summaryPoints}>{Number(wallet?.points ?? 0)}</Text>
            </View>
          </View>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.filterList}
          >
            {FILTERS.map((filter) => {
              const active = activeFilter === filter.key;
              return (
                <TouchableOpacity
                  key={filter.key}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => setActiveFilter(filter.key)}
                  accessibilityRole="button"
                >
                  <Text style={[styles.filterText, active && styles.filterTextActive]}>{filter.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {groupedTransactions.length ? (
            groupedTransactions.map((group) => (
              <View key={group.title} style={styles.dayGroup}>
                <Text style={styles.dayTitle}>{group.title}</Text>
                <View style={styles.transactionList}>
                  {group.data.map((tx, index) => {
                    const amount = Number(tx.amount_mxn ?? 0);
                    const points = Number(tx.points_delta ?? 0);
                    const kind = getTransactionKind(tx);
                    const kindStyles = getKindStyles(kind);
                    const amountPrefix = amount > 0 ? '+' : amount < 0 ? '-' : '';

                    return (
                      <View key={`${tx.created_at}-${index}`} style={styles.transactionRow}>
                        <View style={[styles.transactionIconWrap, kindStyles.iconWrap]}>
                          <Ionicons name={getTransactionIcon(kind)} size={18} color={kindStyles.iconColor} />
                        </View>
                        <View style={styles.transactionInfo}>
                          <Text style={styles.transactionTitle} numberOfLines={2}>
                            {getTransactionTitle(tx)}
                          </Text>
                          <View style={styles.transactionMetaRow}>
                            <Text style={styles.transactionMeta}>{formatTransactionTime(tx.created_at)}</Text>
                            <Text style={styles.metaDot}>·</Text>
                            <Text style={styles.transactionMeta}>
                              Saldo ${Number(tx.balance_after_mxn ?? 0).toFixed(2)}
                            </Text>
                          </View>
                        </View>
                        <View style={styles.transactionTotals}>
                          <Text style={[styles.transactionAmount, kindStyles.amount]}>
                            {amountPrefix}${Math.abs(amount).toFixed(2)}
                          </Text>
                          <View style={[styles.pointsBadge, kindStyles.badge]}>
                            <Text style={styles.transactionPoints}>
                              {points >= 0 ? '+' : ''}
                              {points} pts
                            </Text>
                          </View>
                        </View>
                      </View>
                    );
                  })}
                </View>
              </View>
            ))
          ) : (
            <View style={styles.emptyState}>
              <View style={styles.emptyIcon}>
                <Ionicons name="time-outline" size={30} color={Colors.textMuted} />
              </View>
              {wallet?.transactions?.length ? (
                <>
                  <Text style={styles.emptyTitle}>No hay movimientos en este filtro</Text>
                  <Text style={styles.emptyText}>Prueba con otra categoría para ver más actividad.</Text>
                </>
              ) : (
                <>
                  <Text style={styles.emptyTitle}>Aún no hay movimientos</Text>
                  <Text style={styles.emptyText}>
                    Aquí verás tus recargas, compras con saldo y cambios de puntos.
                  </Text>
                </>
              )}
            </View>
          )}
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 10,
  },
  backButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 20,
  },
  refreshButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 20,
    backgroundColor: Colors.surface,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  headerTitle: {
    fontFamily: FontFamily.heading,
    fontSize: 22,
    color: Colors.text,
  },
  loadingWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 40,
    gap: 18,
  },
  summaryBand: {
    minHeight: 96,
    borderRadius: 22,
    backgroundColor: Colors.primary,
    padding: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    ...Shadows.md,
  },
  summaryLabel: {
    fontSize: 12,
    fontWeight: '800',
    color: Colors.accentLight,
    textTransform: 'uppercase',
  },
  summaryValue: {
    marginTop: 6,
    fontSize: 28,
    fontWeight: '900',
    color: Colors.surface,
  },
  summaryDivider: {
    width: 1,
    height: 48,
    backgroundColor: 'rgba(255,255,255,0.18)',
  },
  summaryRight: {
    alignItems: 'flex-end',
  },
  summaryPoints: {
    marginTop: 6,
    fontSize: 24,
    fontWeight: '900',
    color: Colors.accentLight,
  },
  filterList: {
    gap: 8,
    paddingRight: 8,
  },
  filterChip: {
    paddingHorizontal: 16,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.surface,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  filterChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.textMuted,
  },
  filterTextActive: {
    color: Colors.surface,
  },
  dayGroup: {
    gap: 10,
  },
  dayTitle: {
    fontSize: 13,
    fontWeight: '900',
    color: Colors.textMuted,
    textTransform: 'uppercase',
  },
  transactionList: {
    gap: 10,
  },
  transactionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: Colors.surface,
    borderWidth: 1,
    borderColor: Colors.borderLight,
    borderRadius: 18,
    padding: 14,
    ...Shadows.sm,
  },
  transactionIconWrap: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconWrapTopup: {
    backgroundColor: Colors.successLight,
  },
  iconWrapSpend: {
    backgroundColor: Colors.errorLight,
  },
  iconWrapPoints: {
    backgroundColor: `${Colors.accent}22`,
  },
  transactionInfo: {
    flex: 1,
    minWidth: 0,
  },
  transactionTitle: {
    fontSize: 14,
    lineHeight: 18,
    fontWeight: '900',
    color: Colors.primary,
  },
  transactionMetaRow: {
    marginTop: 5,
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 5,
  },
  transactionMeta: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  metaDot: {
    fontSize: 11,
    fontWeight: '900',
    color: Colors.accentLight,
  },
  transactionTotals: {
    alignItems: 'flex-end',
    gap: 6,
  },
  transactionAmount: {
    fontSize: 15,
    fontWeight: '900',
  },
  pointsBadge: {
    minHeight: 24,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 12,
  },
  pointsBadgeNeutral: {
    backgroundColor: Colors.borderLight,
  },
  pointsBadgeActive: {
    backgroundColor: `${Colors.accent}18`,
  },
  transactionPoints: {
    fontSize: 11,
    fontWeight: '900',
    color: Colors.textSecondary,
  },
  positive: {
    color: Colors.success,
  },
  negative: {
    color: Colors.error,
  },
  neutralAmount: {
    color: Colors.textSecondary,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 44,
    paddingHorizontal: 24,
    borderRadius: 22,
    backgroundColor: Colors.surface,
    borderWidth: 1,
    borderColor: Colors.border,
    gap: 10,
  },
  emptyIcon: {
    width: 54,
    height: 54,
    borderRadius: 27,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.borderLight,
  },
  emptyTitle: {
    marginTop: 4,
    fontSize: 16,
    fontWeight: '900',
    color: Colors.primary,
    textAlign: 'center',
  },
  emptyText: {
    fontSize: 13,
    lineHeight: 19,
    textAlign: 'center',
    color: Colors.textMuted,
  },
});
