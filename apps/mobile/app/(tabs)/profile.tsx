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
import { Colors, Shadows } from '../../theme';

export default function ProfileScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const setUser = useUserStore((s) => s.setUser);
  const logoutStore = useUserStore((s) => s.logout);

  const [uploading, setUploading] = useState(false);

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
