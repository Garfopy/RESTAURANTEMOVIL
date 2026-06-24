import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useUserStore } from '../../store/user.store';
import { apiClient } from '../../services/api';
import { logout } from '../../services/auth.service';
import { getRewardsWallet, type RewardTransaction, type RewardsWallet } from '../../services/rewards.service';
import { Colors, Shadows } from '../../theme';

export default function ProfileScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const setUser = useUserStore((s) => s.setUser);
  const logoutStore = useUserStore((s) => s.logout);

  const [uploading, setUploading] = useState(false);
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [walletLoading, setWalletLoading] = useState(false);

  React.useEffect(() => {
    let cancelled = false;
    async function loadWallet() {
      setWalletLoading(true);
      try {
        const nextWallet = await getRewardsWallet();
        if (!cancelled) setWallet(nextWallet);
      } catch (error) {
        console.warn('No se pudo cargar Saldo Amare', error);
      } finally {
        if (!cancelled) setWalletLoading(false);
      }
    }
    void loadWallet();
    return () => {
      cancelled = true;
    };
  }, []);

  function formatTransactionAmount(tx: RewardTransaction): string {
    const amount = Number(tx.amount_mxn || 0);
    const prefix = amount > 0 ? '+' : '';
    return `${prefix}$${amount.toFixed(2)}`;
  }

  function formatTransactionDate(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
  }

  async function handlePickImage() {
    Alert.alert('Foto de perfil', '¿De dónde quieres obtener la imagen?', [
      { text: 'Cámara', onPress: () => openPicker(true) },
      { text: 'Galería', onPress: () => openPicker(false) },
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }

  async function openPicker(isCamera: boolean) {
    const permissionResult = isCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permissionResult.granted) {
      Alert.alert('Permiso necesario', 'Se requiere acceso para poder cambiar la foto.');
      return;
    }

    const result = isCamera
      ? await ImagePicker.launchCameraAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.6 })
      : await ImagePicker.launchImageLibraryAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.6 });

    if (!result.canceled && result.assets[0].uri) {
      uploadAvatar(result.assets[0].uri);
    }
  }

  async function uploadAvatar(uri: string) {
    setUploading(true);
    try {
      const formData = new FormData();
      const filename = uri.split('/').pop() || 'avatar.jpg';
      const match = /\.(\w+)$/.exec(filename);
      const type = match ? `image/${match[1]}` : 'image/jpeg';

      formData.append(
        'foto',
        {
          uri,
          name: filename,
          type,
        } as any
      );

      const response = await apiClient.post('/profile/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 60000,
      });

      if (response.data.foto_url && user) {
        setUser({ ...user, foto_url: response.data.foto_url });
        Alert.alert('Éxito', 'Tu foto de perfil ha sido actualizada.');
      }
    } catch (error) {
      console.error('Error uploading avatar:', error);
      Alert.alert('Error', 'No se pudo subir la imagen al servidor.');
    } finally {
      setUploading(false);
    }
  }

  async function handleLogout() {
    Alert.alert('Cerrar sesión', '¿Seguro que quieres salir?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Salir',
        style: 'destructive',
        onPress: async () => {
          try {
            await logout();
          } catch {}
          await logoutStore();
        },
      },
    ]);
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Perfil</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.avatarSection}>
          <View style={styles.avatarContainer}>
            <View style={styles.avatar}>
              {user?.foto_url ? (
                <Image source={{ uri: user.foto_url }} style={styles.avatarImg} cachePolicy="disk" />
              ) : (
                <Text style={styles.avatarLetter}>{user?.nombre?.[0]?.toUpperCase() ?? '?'}</Text>
              )}
            </View>
            <TouchableOpacity
              style={styles.editBadge}
              activeOpacity={0.9}
              onPress={handlePickImage}
              disabled={uploading}
            >
              {uploading ? (
                <ActivityIndicator size="small" color="#FFFFFF" />
              ) : (
                <Ionicons name="camera" size={16} color="#FFFFFF" />
              )}
            </TouchableOpacity>
          </View>
          <Text style={styles.nombre}>{user?.nombre ?? '—'}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
        </View>

        <View style={styles.walletCard}>
          <View style={styles.walletHeader}>
            <View style={styles.walletIcon}>
              <Ionicons name="sparkles" size={22} color="#FFFFFF" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.walletTitle}>Saldo Amare</Text>
              <Text style={styles.walletSubtitle}>Saldo simulado · 10% pagando con saldo</Text>
            </View>
            {walletLoading ? <ActivityIndicator size="small" color="#065F46" /> : null}
          </View>
          <View style={styles.walletStats}>
            <View>
              <Text style={styles.walletStatLabel}>Disponible</Text>
              <Text style={styles.walletStatValue}>${Number(wallet?.balance_mxn ?? 0).toFixed(2)}</Text>
            </View>
            <View>
              <Text style={styles.walletStatLabel}>Puntos</Text>
              <Text style={styles.walletStatValue}>{Number(wallet?.points ?? 0)}</Text>
            </View>
            <View>
              <Text style={styles.walletStatLabel}>Canjeable</Text>
              <Text style={styles.walletStatValue}>${Number(wallet?.points_value_mxn ?? 0).toFixed(2)}</Text>
            </View>
          </View>

          {wallet?.transactions?.length ? (
            <View style={styles.walletHistory}>
              <View style={styles.walletHistoryHeader}>
                <Text style={styles.walletHistoryTitle}>Movimientos recientes</Text>
                <Text style={styles.walletHistoryCount}>{wallet.transactions.length}</Text>
              </View>
              {wallet.transactions.slice(0, 3).map((tx, index) => (
                <View key={`${tx.created_at}-${index}`} style={styles.walletTxRow}>
                  <View style={styles.walletTxIcon}>
                    <Ionicons
                      name={tx.type === 'wallet_payment' ? 'bag-check-outline' : 'sparkles-outline'}
                      size={15}
                      color="#047857"
                    />
                  </View>
                  <View style={styles.walletTxCopy}>
                    <Text style={styles.walletTxTitle} numberOfLines={1}>
                      {tx.description || (tx.type === 'wallet_payment' ? 'Pago con Saldo Amare' : 'Movimiento Amare')}
                    </Text>
                    <Text style={styles.walletTxMeta} numberOfLines={1}>
                      {formatTransactionDate(tx.created_at)}
                      {tx.points_delta ? ` · ${tx.points_delta > 0 ? '+' : ''}${tx.points_delta} pts` : ''}
                    </Text>
                  </View>
                  <Text style={[styles.walletTxAmount, Number(tx.amount_mxn) < 0 && styles.walletTxAmountNegative]}>
                    {formatTransactionAmount(tx)}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}
        </View>

        <View style={styles.menuContainer}>
          <Text style={styles.sectionTitle}>Mi Cuenta</Text>
          <View style={styles.section}>
            <MenuItem
              icon="location"
              label="Mis direcciones"
              color="#3B82F6"
              onPress={() => router.push('/profile/addresses')}
              showDivider
            />
            <MenuItem
              icon="bag"
              label="Historial de pedidos"
              color="#10B981"
              onPress={() => router.push('/(tabs)/orders')}
              showDivider
            />
            <MenuItem
              icon="people"
              label="Perfil social"
              color="#8B5CF6"
              onPress={() => router.push('/profile/social' as any)}
              showDivider
            />
            <MenuItem
              icon="heart"
              label="Favoritos"
              color="#EF4444"
              onPress={() => router.push('/(tabs)/favorites')}
            />
          </View>

          <Text style={styles.sectionTitle}>Ayuda y Soporte</Text>
          <View style={styles.section}>
            <MenuItem
              icon="help-circle"
              label="Centro de ayuda"
              color="#8B5CF6"
              onPress={() => Alert.alert('Ayuda', 'Próximamente...')}
            />
          </View>
        </View>

        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout} activeOpacity={0.7}>
          <Ionicons name="log-out" size={20} color={Colors.error || '#EF4444'} />
          <Text style={styles.logoutText}>Cerrar sesión</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

function MenuItem({
  icon,
  label,
  color,
  onPress,
  showDivider = false,
}: {
  icon: string;
  label: string;
  color: string;
  onPress: () => void;
  showDivider?: boolean;
}) {
  return (
    <TouchableOpacity style={styles.menuItem} onPress={onPress} activeOpacity={0.5}>
      <View style={[styles.iconBg, { backgroundColor: `${color}12` }]}>
        <Ionicons name={icon as never} size={20} color={color} />
      </View>
      <View style={[styles.menuItemContent, showDivider && styles.bottomBorder]}>
        <Text style={styles.menuLabel}>{label}</Text>
        <Ionicons name="chevron-forward" size={18} color="#9CA3AF" style={{ marginRight: 16 }} />
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background || '#F9FAFB',
  },
  header: {
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 16,
  },
  headerTitle: {
    fontSize: 34,
    fontWeight: '800',
    color: Colors.text || '#111827',
    letterSpacing: -0.8,
    lineHeight: 42,
    paddingTop: 4,
  },
  content: {
    paddingHorizontal: 24,
    paddingBottom: 120,
  },
  avatarSection: {
    alignItems: 'center',
    paddingVertical: 24,
  },
  avatarContainer: {
    position: 'relative',
    marginBottom: 16,
    ...Shadows.md,
  },
  avatar: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: Colors.primary || '#111827',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 4,
    borderColor: '#FFFFFF',
    overflow: 'hidden',
  },
  avatarImg: {
    width: '100%',
    height: '100%',
  },
  editBadge: {
    position: 'absolute',
    bottom: 2,
    right: 2,
    backgroundColor: '#111827',
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: '#FFFFFF',
    ...Shadows.sm,
  },
  avatarLetter: {
    color: '#FFFFFF',
    fontSize: 38,
    fontWeight: '800',
  },
  nombre: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text || '#111827',
    letterSpacing: -0.5,
    marginBottom: 4,
  },
  email: {
    fontSize: 14,
    fontWeight: '500',
    color: '#6B7280',
  },
  walletCard: {
    marginBottom: 22,
    padding: 16,
    borderRadius: 22,
    backgroundColor: '#ECFDF5',
    borderWidth: 1,
    borderColor: '#BBF7D0',
    ...Shadows.sm,
  },
  walletHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 14,
  },
  walletIcon: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: '#059669',
    alignItems: 'center',
    justifyContent: 'center',
  },
  walletTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: '#064E3B',
  },
  walletSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '600',
    color: '#047857',
  },
  walletStats: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  walletStatLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#047857',
  },
  walletStatValue: {
    marginTop: 3,
    fontSize: 17,
    fontWeight: '900',
    color: '#064E3B',
  },
  walletHistory: {
    marginTop: 16,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: '#BBF7D0',
    gap: 10,
  },
  walletHistoryHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  walletHistoryTitle: {
    fontSize: 12,
    fontWeight: '900',
    color: '#065F46',
    textTransform: 'uppercase',
  },
  walletHistoryCount: {
    minWidth: 24,
    height: 24,
    borderRadius: 12,
    overflow: 'hidden',
    textAlign: 'center',
    textAlignVertical: 'center',
    backgroundColor: '#BBF7D0',
    color: '#065F46',
    fontSize: 11,
    fontWeight: '900',
    paddingTop: 3,
  },
  walletTxRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
  },
  walletTxIcon: {
    width: 30,
    height: 30,
    borderRadius: 11,
    backgroundColor: '#D1FAE5',
    alignItems: 'center',
    justifyContent: 'center',
  },
  walletTxCopy: {
    flex: 1,
    minWidth: 0,
  },
  walletTxTitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#064E3B',
  },
  walletTxMeta: {
    marginTop: 1,
    fontSize: 11,
    fontWeight: '700',
    color: '#047857',
  },
  walletTxAmount: {
    fontSize: 12,
    fontWeight: '900',
    color: '#047857',
  },
  walletTxAmountNegative: {
    color: '#9F1239',
  },
  menuContainer: {
    gap: 20,
    marginTop: 6,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 1.2,
    marginLeft: 8,
    marginBottom: -8,
  },
  section: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    ...Shadows.sm,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingLeft: 16,
  },
  menuItemContent: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 18,
  },
  bottomBorder: {
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  iconBg: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  menuLabel: {
    flex: 1,
    fontSize: 16,
    color: Colors.text || '#111827',
    fontWeight: '600',
    letterSpacing: -0.2,
  },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    marginTop: 36,
    backgroundColor: '#FEF2F2',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#FEE2E2',
  },
  logoutText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.error || '#EF4444',
  },
});
