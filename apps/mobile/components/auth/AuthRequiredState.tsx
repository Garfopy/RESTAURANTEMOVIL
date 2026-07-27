import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { saveAuthReturnTo } from '../../services/auth-gate.service';
import { Colors } from '../../theme';

type AuthRequiredStateProps = {
  title: string;
  message: string;
  returnTo: string;
  icon?: keyof typeof Ionicons.glyphMap;
  primaryLabel?: string;
  secondaryLabel?: string;
  benefits?: string[];
};

export function AuthRequiredState({
  title,
  message,
  returnTo,
  icon = 'person-circle-outline',
  primaryLabel = 'Crear cuenta gratis',
  secondaryLabel = 'Ya tengo cuenta',
  benefits = ['Ofertas', 'Historial', 'Favoritos'],
}: AuthRequiredStateProps) {
  const router = useRouter();

  function goTo(pathname: '/(auth)/login' | '/(auth)/register') {
    void saveAuthReturnTo(returnTo);
    router.push({ pathname, params: { returnTo } } as never);
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.iconWrap}>
        <Ionicons name={icon} size={42} color={Colors.primary || '#111827'} />
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      <View style={styles.benefitsRow}>
        {benefits.slice(0, 3).map((benefit) => (
          <View key={benefit} style={styles.benefitChip}>
            <Ionicons name="checkmark-circle-outline" size={13} color={Colors.primary || '#111827'} />
            <Text style={styles.benefitText}>{benefit}</Text>
          </View>
        ))}
      </View>
      <TouchableOpacity style={styles.primaryButton} onPress={() => goTo('/(auth)/register')} activeOpacity={0.86}>
        <Text style={styles.primaryText}>{primaryLabel}</Text>
      </TouchableOpacity>
      <TouchableOpacity style={styles.secondaryButton} onPress={() => goTo('/(auth)/login')} activeOpacity={0.86}>
        <Text style={styles.secondaryText}>{secondaryLabel}</Text>
      </TouchableOpacity>
      <TouchableOpacity style={styles.exploreButton} onPress={() => router.replace('/(tabs)' as never)} activeOpacity={0.86}>
        <Text style={styles.exploreText}>Seguir explorando menu</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 28,
    paddingBottom: 80,
    backgroundColor: Colors.background || '#F9FAFB',
  },
  iconWrap: {
    width: 78,
    height: 78,
    borderRadius: 39,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    marginBottom: 18,
  },
  title: {
    fontSize: 24,
    fontWeight: '900',
    color: Colors.text || '#111827',
    textAlign: 'center',
  },
  message: {
    marginTop: 8,
    fontSize: 15,
    lineHeight: 21,
    color: '#6B7280',
    textAlign: 'center',
  },
  benefitsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: 8,
    marginTop: 16,
  },
  benefitChip: {
    minHeight: 30,
    borderRadius: 15,
    paddingHorizontal: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  benefitText: {
    color: Colors.text || '#111827',
    fontSize: 11,
    fontWeight: '800',
  },
  primaryButton: {
    minHeight: 50,
    minWidth: 190,
    borderRadius: 16,
    backgroundColor: Colors.primary || '#111827',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginTop: 22,
  },
  primaryText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '900',
  },
  secondaryButton: {
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginTop: 6,
  },
  secondaryText: {
    color: Colors.primary || '#111827',
    fontSize: 14,
    fontWeight: '900',
  },
  exploreButton: {
    minHeight: 40,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginTop: 2,
  },
  exploreText: {
    color: '#6B7280',
    fontSize: 13,
    fontWeight: '800',
  },
});
