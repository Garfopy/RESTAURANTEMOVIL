import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { useUserStore } from '../../store/user.store';
import { apiClient } from '../../services/api';
import { logout } from '../../services/auth.service';
import { Colors, Spacing, Typography, Shadows } from '../../theme';

export default function ProfileScreen() {
  const router = useRouter();
  const user = useUserStore((s) => s.user);
  const setUser = useUserStore((s) => s.setUser);
  const logoutStore = useUserStore((s) => s.logout);

  const [uploading, setUploading] = useState(false);

  async function handlePickImage() {
    // Solicitar opciones: Cámara o Galería
    Alert.alert(
      'Foto de perfil',
      '¿De dónde quieres obtener la imagen?',
      [
        { text: 'Cámara', onPress: () => openPicker(true) },
        { text: 'Galería', onPress: () => openPicker(false) },
        { text: 'Cancelar', style: 'cancel' },
      ]
    );
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
      const type = match ? `image/${match[1]}` : `image/jpeg`;

      formData.append('foto', {
        uri,
        name: filename,
        type,
      } as any);

      // Asumiendo que tu API tiene este endpoint para actualizar el perfil
      const response = await apiClient.post('/profile/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 60000, // Aumentamos a 60 segundos para permitir la subida de archivos
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
            {user?.foto_url ? (
              <Image source={{ uri: user.foto_url }} style={styles.avatarImg} />
            ) : (
              <Text style={styles.avatarLetter}>
                {user?.nombre?.[0]?.toUpperCase() ?? '?'}
              </Text>
            )}
            
            <TouchableOpacity 
              style={styles.editBadge} 
              activeOpacity={0.8}
              onPress={handlePickImage}
              disabled={uploading}
            >
              {uploading 
                ? <ActivityIndicator size="small" color={Colors.white} />
                : <Ionicons name="camera" size={16} color={Colors.white} />
              }
            </TouchableOpacity>
          </View>
          <Text style={styles.nombre}>{user?.nombre ?? '—'}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
        </View>

        {/* Opciones */}
        <View style={styles.menuContainer}>
          <Text style={styles.sectionTitle}>Mi Cuenta</Text>
        <View style={styles.section}>
          <MenuItem
            icon="location-outline"
            label="Mis direcciones"
            color="#3B82F6"
            onPress={() => router.push('/profile/addresses')}
          />
          <MenuItem
            icon="bag-outline"
            label="Historial de pedidos"
            color="#10B981"
            onPress={() => router.push('/(tabs)/orders')}
          />
          <MenuItem
            icon="heart-outline"
            label="Favoritos"
            color="#EF4444"
            onPress={() => router.push('/(tabs)/favorites')}
          />
        </View>

        <Text style={styles.sectionTitle}>Ayuda y Soporte</Text>
        <View style={styles.section}>
          <MenuItem
            icon="help-circle-outline"
            label="Centro de ayuda"
            color="#8B5CF6"
            onPress={() => Alert.alert('Ayuda', 'Próximamente...')}
          />
        </View>
        </View>

        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
          <Ionicons name="log-out-outline" size={20} color={Colors.error} />
          <Text style={styles.logoutText}>Cerrar sesión</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

function MenuItem({ icon, label, color, onPress }: { icon: string; label: string; color: string; onPress: () => void }) {
  return (
    <TouchableOpacity style={styles.menuItem} onPress={onPress} activeOpacity={0.7}>
      <View style={[styles.iconBg, { backgroundColor: color + '15' }]}>
        <Ionicons name={icon as never} size={20} color={color} />
      </View>
      <Text style={styles.menuLabel}>{label}</Text>
      <Ionicons name="chevron-forward" size={18} color={Colors.textMuted} />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  header: {
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  headerTitle: { fontSize: 24, fontWeight: '800', color: Colors.text, letterSpacing: -0.5 },
  content: { paddingHorizontal: 20, paddingBottom: 120 },
  avatarSection: { alignItems: 'center', paddingVertical: 20, gap: 4 },
  avatar: {
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: Colors.primary || '#111827',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
    position: 'relative',
    ...Shadows.md,
  },
  avatarImg: {
    width: 90,
    height: 90,
    borderRadius: 45,
  },
  editBadge: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    backgroundColor: '#374151',
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: Colors.background,
  },
  avatarLetter: { color: Colors.white, fontSize: 36, fontWeight: '800' },
  nombre: { fontSize: 22, fontWeight: '800', color: Colors.text, letterSpacing: -0.5 },
  email: { fontSize: 14, color: Colors.textMuted },
  menuContainer: { gap: 16, marginTop: 20 },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginLeft: 4,
  },
  section: {
    backgroundColor: '#F9FAFB',
    borderRadius: 20,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  iconBg: { width: 38, height: 38, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  menuLabel: { flex: 1, fontSize: 15, color: Colors.text, fontWeight: '500' },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    marginTop: 40,
    backgroundColor: '#FEF2F2',
    borderRadius: 12,
  },
  logoutText: { fontSize: 15, fontWeight: '600', color: Colors.error },
});
