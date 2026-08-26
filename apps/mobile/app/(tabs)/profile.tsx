import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  Switch,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useUserStore } from '../../store/user.store';
import { apiClient } from '../../services/api';
import { deleteAccount, logout, updateProfileSettings } from '../../services/auth.service';
import { ensureNotificationPermission } from '../../services/app-permissions.service';
import { isPushRegistrationEnabled, registerPushNotifications } from '../../services/push-notifications.service';
import { getRewardsWallet, type RewardsWallet } from '../../services/rewards.service';
import { STRIPE_IS_CONFIGURED } from '../../constants/stripe';
import { WALLET_ENABLED } from '../../constants/features';
import { AuthRequiredState } from '../../components/auth/AuthRequiredState';
import { Colors, Shadows, FontFamily } from '../../theme';

export default function ProfileScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const token = useUserStore((s) => s.token);
  const setUser = useUserStore((s) => s.setUser);
  const logoutStore = useUserStore((s) => s.logout);

  const [uploading, setUploading] = useState(false);
  const [wallet, setWallet] = useState<RewardsWallet | null>(null);
  const [walletLoading, setWalletLoading] = useState(false);
  const [savingMarketing, setSavingMarketing] = useState(false);

  useFocusEffect(
    React.useCallback(() => {
      if (!token || !WALLET_ENABLED) return;
      void refreshWallet();
    }, [token])
  );

  if (!token) {
    return (
      <AuthRequiredState
        icon="person-outline"
        title="Crea tu cuenta"
        message="Accede a ofertas, direcciones guardadas, beneficios, historial y preferencias personales."
        benefits={['Direcciones', 'Rewards', 'Preferencias']}
        returnTo="/(tabs)/profile"
      />
    );
  }

  async function refreshWallet() {
    setWalletLoading(true);
    try {
      setWallet(await getRewardsWallet());
    } catch (error) {
      console.warn('No se pudo cargar el saldo', error);
    } finally {
      setWalletLoading(false);
    }
  }

  async function handleMarketingPreference(enabled: boolean) {
    if (!user || savingMarketing) return;

    try {
      setSavingMarketing(true);
      if (enabled) {
        const permission = await ensureNotificationPermission({ explainIfBlocked: true });
        if (!permission.granted) return;
      }

      const updated = await updateProfileSettings({ marketing_opt_in: enabled });
      setUser(updated);
      if (enabled) {
        void registerPushNotifications({ reason: 'marketing-opt-in', userId: updated.id });
      }
    } catch {
      Alert.alert('No se pudo guardar', 'Intenta nuevamente en un momento.');
    } finally {
      setSavingMarketing(false);
    }
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
      ? await ImagePicker.launchCameraAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.5 })
      : await ImagePicker.launchImageLibraryAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.5 });

    if (!result.canceled && result.assets[0].uri) {
      await uploadAvatar(result.assets[0].uri);
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

  function handleDeleteAccount() {
    Alert.alert(
      'Eliminar cuenta',
      'Se borraran tus datos personales, perfil social, direcciones, favoritos y tokens de notificaciones. Conservaremos pedidos, pagos y facturas cuando sea necesario por obligaciones operativas o fiscales.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Continuar',
          style: 'destructive',
          onPress: confirmDeleteAccount,
        },
      ]
    );
  }

  function confirmDeleteAccount() {
    Alert.alert(
      'Confirmar eliminacion',
      'Esta accion no se puede deshacer. Tu cuenta dejara de estar disponible inmediatamente.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Eliminar cuenta',
          style: 'destructive',
          onPress: async () => {
            try {
              await deleteAccount();
            } catch (error) {
              console.warn('No se pudo eliminar la cuenta:', error);
              Alert.alert('No se pudo eliminar', 'Intenta de nuevo o contacta a soporte desde los canales oficiales.');
              return;
            }
            await logoutStore();
          },
        },
      ]
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.swipeSurface}>
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
                <ActivityIndicator size="small" color={Colors.white} />
              ) : (
                <Ionicons name="camera" size={16} color={Colors.white} />
              )}
            </TouchableOpacity>
          </View>
          <Text style={styles.nombre}>{user?.nombre ?? '—'}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
        </View>

        {WALLET_ENABLED ? (
          <View style={styles.rewardsSummaryGrid}>
            <View style={styles.summaryCardsRow}>
              <TouchableOpacity
                style={[styles.summaryCard, styles.summaryCardBalance]}
                activeOpacity={0.88}
                onPress={() => router.push('/profile/activity' as any)}
              >
                <View style={styles.summaryTopRow}>
                  <View style={styles.summaryIconWrap}>
                    <Ionicons name="wallet-outline" size={20} color={Colors.primary} />
                  </View>
                  {walletLoading ? <ActivityIndicator size="small" color={Colors.primary} /> : <Ionicons name="chevron-forward" size={18} color={Colors.primaryLight} />}
                </View>
                <Text style={styles.summaryTitle}>Saldo</Text>
                <Text style={styles.summaryValue}>${Number(wallet?.balance_mxn ?? 0).toFixed(2)}</Text>
                <Text style={styles.summaryHint}>Toca para ver tu actividad</Text>
              </TouchableOpacity>

              <View style={[styles.summaryCard, styles.summaryCardPoints]}>
                <View style={styles.summaryTopRow}>
                  <View style={styles.summaryIconWrap}>
                    <Ionicons name="trophy-outline" size={20} color={Colors.accentDark} />
                  </View>
                  {walletLoading ? <ActivityIndicator size="small" color={Colors.accentDark} /> : null}
                </View>
                <Text style={styles.summaryTitle}>Puntos</Text>
                <Text style={styles.summaryValue}>{Number(wallet?.points ?? 0)}</Text>
                <Text style={styles.summaryHint}>1 punto = 1 peso. Puedes usarlos al pagar.</Text>
              </View>
            </View>
          </View>
        ) : null}

        <View style={styles.menuContainer}>
          <Text style={styles.sectionTitle}>Mi Cuenta</Text>
          <View style={styles.section}>
            <MenuItem
              icon="bag"
              label="Historial de pedidos"
              color={Colors.primaryLight}
              onPress={() => router.push('/(tabs)/orders')}
              showDivider
            />
            {WALLET_ENABLED ? (
              <MenuItem
                icon="time"
                label="Actividad reciente"
                color={Colors.accent}
                onPress={() => router.push('/profile/activity' as any)}
                showDivider
              />
            ) : null}
            <MenuItem
              icon="heart"
              label="Favoritos"
              color={Colors.error}
              onPress={() => router.push('/(tabs)/favorites')}
            />
          </View>

          {isPushRegistrationEnabled() ? (
            <>
              <Text style={styles.sectionTitle}>Notificaciones</Text>
              <View style={styles.section}>
                <View style={styles.preferenceRow}>
                  <View style={[styles.iconBg, { backgroundColor: `${Colors.accent}20` }]}>
                    <Ionicons name="notifications" size={20} color={Colors.accentDark} />
                  </View>
                  <View style={styles.preferenceContent}>
                    <Text style={styles.menuLabel}>Promociones y beneficios</Text>
                    <Text style={styles.preferenceHint}>
                      Recibe avisos de promociones disponibles para tu cuenta.
                    </Text>
                  </View>
                  {savingMarketing ? (
                    <ActivityIndicator size="small" color={Colors.primary} />
                  ) : (
                    <Switch
                      accessibilityLabel="Recibir promociones y beneficios"
                      value={Boolean(user?.marketing_opt_in)}
                      onValueChange={(enabled) => void handleMarketingPreference(enabled)}
                      trackColor={{ false: Colors.border, true: Colors.accent }}
                      thumbColor={Colors.surface}
                    />
                  )}
                </View>
              </View>
            </>
          ) : null}

          <Text style={styles.sectionTitle}>Ayuda y Soporte</Text>
          <View style={styles.section}>
            <MenuItem
              icon="help-circle"
              label="Centro de ayuda"
              color={Colors.primaryLight}
              onPress={() => router.push('/profile/help' as any)}
              showDivider
            />
            <MenuItem
              icon="document-text"
              label="Terminos y aviso legal"
              color={Colors.accentDark}
              onPress={() => router.push('/legal/terms' as any)}
              showDivider
            />
            <MenuItem
              icon="shield-checkmark"
              label="Privacidad"
              color={Colors.primary}
              onPress={() => router.push('/legal/privacy' as any)}
              showDivider
            />
            <MenuItem
              icon="trash"
              label="Eliminar cuenta"
              color={Colors.error}
              onPress={handleDeleteAccount}
            />
          </View>
        </View>

        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout} activeOpacity={0.7}>
          <Ionicons name="log-out" size={20} color={Colors.error} />
          <Text style={styles.logoutText}>Cerrar sesión</Text>
        </TouchableOpacity>
      </ScrollView>
      </View>
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
        <Ionicons name="chevron-forward" size={18} color={Colors.textMuted} style={{ marginRight: 16 }} />
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  swipeSurface: {
    flex: 1,
  },
  header: {
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 16,
  },
  headerTitle: {
    fontFamily: FontFamily.heading,
    fontSize: 32,
    color: Colors.text,
    lineHeight: 40,
    paddingTop: 4,
  },
  content: {
    paddingHorizontal: 24,
    paddingBottom: 120,
    gap: 18,
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
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 4,
    borderColor: Colors.surface,
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
    backgroundColor: Colors.primary,
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: Colors.surface,
    ...Shadows.sm,
  },
  avatarLetter: {
    color: Colors.white,
    fontSize: 38,
    fontWeight: '800',
  },
  nombre: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.5,
    marginBottom: 4,
  },
  email: {
    fontSize: 14,
    fontWeight: '500',
    color: Colors.textMuted,
  },
  rewardsSummaryGrid: {
    gap: 14,
  },
  summaryCardsRow: {
    flexDirection: 'row',
    gap: 12,
  },
  summaryCard: {
    flex: 1,
    minWidth: 0,
    borderRadius: 22,
    padding: 16,
    borderWidth: 1,
    ...Shadows.sm,
  },
  summaryCardBalance: {
    backgroundColor: `${Colors.primary}0D`,
    borderColor: `${Colors.primary}22`,
  },
  summaryCardPoints: {
    backgroundColor: `${Colors.accent}18`,
    borderColor: `${Colors.accent}40`,
  },
  summaryTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  summaryIconWrap: {
    width: 42,
    height: 42,
    borderRadius: 14,
    backgroundColor: `${Colors.surface}B0`,
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
    lineHeight: 19,
  },
  summaryValue: {
    marginTop: 8,
    fontSize: 24,
    fontWeight: '900',
    color: Colors.text,
    lineHeight: 30,
  },
  summaryHint: {
    marginTop: 4,
    fontSize: 12,
    color: Colors.textSecondary,
    fontWeight: '600',
    lineHeight: 16,
  },
  menuContainer: {
    gap: 20,
    marginTop: 6,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 1.2,
    marginLeft: 8,
    marginBottom: -8,
  },
  section: {
    backgroundColor: Colors.surface,
    borderRadius: 24,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: Colors.border,
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
    borderBottomColor: Colors.borderLight,
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
    color: Colors.text,
    fontWeight: '600',
    letterSpacing: -0.2,
  },
  preferenceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  preferenceContent: {
    flex: 1,
    marginRight: 12,
  },
  preferenceHint: {
    marginTop: 3,
    fontSize: 12,
    lineHeight: 17,
    color: Colors.textMuted,
    fontWeight: '500',
  },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
    marginTop: 18,
    backgroundColor: Colors.errorLight,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: `${Colors.error}30`,
  },
  logoutText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.error,
  },
});
