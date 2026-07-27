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
};

export function AuthRequiredState({ title, message, returnTo }: AuthRequiredStateProps) {
  const router = useRouter();

  function goTo(pathname: '/(auth)/login' | '/(auth)/register') {
    void saveAuthReturnTo(returnTo);
    router.push({ pathname, params: { returnTo } } as never);
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.iconWrap}>
        <Ionicons name="person-circle-outline" size={42} color={Colors.primary || '#111827'} />
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      <TouchableOpacity style={styles.primaryButton} onPress={() => goTo('/(auth)/login')} activeOpacity={0.86}>
        <Text style={styles.primaryText}>Iniciar sesion</Text>
      </TouchableOpacity>
      <TouchableOpacity style={styles.secondaryButton} onPress={() => goTo('/(auth)/register')} activeOpacity={0.86}>
        <Text style={styles.secondaryText}>Crear cuenta</Text>
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
});
