import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useUserStore } from '../../store/user.store';
import { logout } from '../../services/auth.service';
import { Colors, Spacing, Typography } from '../../theme';

export default function ProfileScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const logoutStore = useUserStore((s) => s.logout);

  async function handleLogout() {
    Alert.alert('Cerrar sesión', '¿Seguro que quieres salir?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Salir',
        style: 'destructive',
        onPress: async () => {
          try { await logout(); } catch {}
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

      <ScrollView contentContainerStyle={styles.content}>
        {/* Avatar */}
        <View style={styles.avatarSection}>
          <View style={styles.avatar}>
            <Text style={styles.avatarLetter}>
              {user?.nombre?.[0]?.toUpperCase() ?? '?'}
            </Text>
          </View>
          <Text style={styles.nombre}>{user?.nombre ?? '—'}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
        </View>

        {/* Opciones */}
        <View style={styles.section}>
          <MenuItem
            icon="location-outline"
            label="Mis direcciones"
            onPress={() => router.push('/profile/addresses')}
          />
          <MenuItem
            icon="bag-outline"
            label="Historial de pedidos"
            onPress={() => router.push('/(tabs)/orders')}
          />
          <MenuItem
            icon="heart-outline"
            label="Favoritos"
            onPress={() => router.push('/(tabs)/favorites')}
          />
        </View>

        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
          <Ionicons name="log-out-outline" size={20} color={Colors.error} />
          <Text style={styles.logoutText}>Cerrar sesión</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

function MenuItem({ icon, label, onPress }: { icon: string; label: string; onPress: () => void }) {
  return (
    <TouchableOpacity style={styles.menuItem} onPress={onPress} activeOpacity={0.7}>
      <Ionicons name={icon as never} size={22} color={Colors.primary} />
      <Text style={styles.menuLabel}>{label}</Text>
      <Ionicons name="chevron-forward" size={18} color={Colors.textMuted} />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: Spacing.base,
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: { ...Typography.h2, fontWeight: '700', color: Colors.text },
  content: { padding: Spacing.base, gap: Spacing.base },
  avatarSection: { alignItems: 'center', paddingVertical: Spacing.xl, gap: Spacing.xs },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: Colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarLetter: { color: Colors.white, fontSize: 32, fontWeight: '700' },
  nombre: { fontSize: 20, fontWeight: '700', color: Colors.text },
  email: { fontSize: 14, color: Colors.textMuted },
  section: {
    backgroundColor: Colors.surface,
    borderRadius: 14,
    overflow: 'hidden',
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.md,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  menuLabel: { flex: 1, fontSize: 15, color: Colors.text, fontWeight: '500' },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    backgroundColor: Colors.errorLight,
    borderRadius: 12,
  },
  logoutText: { fontSize: 15, fontWeight: '600', color: Colors.error },
});
