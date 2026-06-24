import React, { useState } from 'react';
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
import { getRewardsWallet, type RewardsWallet } from '../../services/rewards.service';
import { Colors, Shadows } from '../../theme';

export default function ProfileActivityScreen() {
  const router = useRouter();
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [loading, setLoading] = useState(false);

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

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} accessibilityRole="button">
          <Ionicons name="arrow-back" size={24} color={Colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Actividad reciente</Text>
        <View style={{ width: 24 }} />
      </View>

      {loading ? (
        <View style={styles.loadingWrap}>
          <ActivityIndicator size="large" color={Colors.primary} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.content}>
          <View style={styles.sectionCard}>
            {wallet?.transactions?.length ? (
              wallet.transactions.map((tx, index) => (
                <View key={`${tx.created_at}-${index}`} style={styles.transactionRow}>
                  <View style={styles.transactionInfo}>
                    <Text style={styles.transactionTitle}>{tx.description || 'Movimiento Amare'}</Text>
                    <Text style={styles.transactionMeta}>{tx.created_at}</Text>
                  </View>
                  <View style={styles.transactionTotals}>
                    <Text
                      style={[
                        styles.transactionAmount,
                        Number(tx.amount_mxn ?? 0) >= 0 ? styles.positive : styles.negative,
                      ]}
                    >
                      {Number(tx.amount_mxn ?? 0) >= 0 ? '+' : ''}$
                      {Math.abs(Number(tx.amount_mxn ?? 0)).toFixed(2)}
                    </Text>
                    <Text style={styles.transactionPoints}>
                      {Number(tx.points_delta ?? 0) >= 0 ? '+' : ''}
                      {Number(tx.points_delta ?? 0)} pts
                    </Text>
                  </View>
                </View>
              ))
            ) : (
              <View style={styles.emptyState}>
                <Ionicons name="time-outline" size={30} color="#9CA3AF" />
                <Text style={styles.emptyTitle}>Aún no hay movimientos</Text>
                <Text style={styles.emptyText}>
                  Aquí verás tus recargas, compras con saldo y cambios de puntos.
                </Text>
              </View>
            )}
          </View>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background || '#F9FAFB',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 10,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text || '#111827',
  },
  loadingWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: {
    paddingHorizontal: 20,
    paddingBottom: 40,
  },
  sectionCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
    gap: 12,
    ...Shadows.sm,
  },
  transactionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
    paddingTop: 12,
  },
  transactionInfo: {
    flex: 1,
  },
  transactionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
  },
  transactionMeta: {
    marginTop: 3,
    fontSize: 11,
    color: '#6B7280',
  },
  transactionTotals: {
    alignItems: 'flex-end',
  },
  transactionAmount: {
    fontSize: 13,
    fontWeight: '900',
  },
  transactionPoints: {
    marginTop: 2,
    fontSize: 11,
    fontWeight: '700',
    color: '#6B7280',
  },
  positive: {
    color: '#059669',
  },
  negative: {
    color: '#DC2626',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 28,
    gap: 10,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#111827',
  },
  emptyText: {
    fontSize: 13,
    lineHeight: 19,
    textAlign: 'center',
    color: '#6B7280',
  },
});
