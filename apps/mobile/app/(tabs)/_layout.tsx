import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Platform, View, StyleSheet, Animated } from 'react-native';
import React, { useRef, useEffect, memo } from 'react';
import { Colors } from '../../theme';

type IoniconName = keyof typeof Ionicons.glyphMap;

const TABS: { name: string; title: string; icon: IoniconName; iconFocused: IoniconName }[] = [
  { name: 'index',      title: 'Inicio',      icon: 'home-outline',           iconFocused: 'home' },
  { name: 'orders',     title: 'Pedidos',     icon: 'bag-outline',            iconFocused: 'bag' },
  { name: 'favorites',  title: 'Favoritos',   icon: 'heart-outline',          iconFocused: 'heart' },
  { name: 'promotions', title: 'Promos',      icon: 'pricetag-outline',       iconFocused: 'pricetag' },
  { name: 'profile',    title: 'Perfil',      icon: 'person-circle-outline',  iconFocused: 'person-circle' },
];

// Memoizamos para evitar cálculos innecesarios durante la navegación
const TabIcon = memo(({ focused, color, icon, iconFocused }: { focused: boolean; color: string; icon: IoniconName; iconFocused: IoniconName }) => {
  // Animación de escala para el efecto "Liquid"
  const scaleAnim = useRef(new Animated.Value(focused ? 1.2 : 1)).current;

  useEffect(() => {
    Animated.spring(scaleAnim, {
      toValue: focused ? 1.2 : 1,
      friction: 10, // Más rigidez para evitar rebotes largos
      tension: 140, // Más velocidad de respuesta
      useNativeDriver: true,
    }).start();
  }, [focused]);

  return (
    <View style={styles.iconContainer} pointerEvents="none">
      {/* Cápsula de fondo activo (Efecto Liquid Pill) */}
      {focused && <View style={[styles.activePill, { backgroundColor: color + '10' }]} />}
      <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
        <Ionicons
          name={focused ? iconFocused : icon}
          size={24}
          color={color}
        />
      </Animated.View>
    </View>
  );
});

export default function TabsLayout() {
  return (
    <Tabs
      sceneContainerStyle={{ backgroundColor: Colors.background }}
      screenOptions={{
        headerShown: false,
        tabBarAllowFontScaling: false,
        tabBarShowLabel: true,
        // Mejora el rendimiento al no renderizar todo el árbol si no es necesario
        lazy: true,
        // Evita que las pantallas inactivas consuman recursos de GPU/CPU
        detachInactiveScreens: true,

        tabBarStyle: {
          position: 'absolute',
          left: 16,
          right: 16,
          bottom: Platform.OS === 'ios' ? 24 : 12,

          height: 77, // Aumentamos la altura para dar espacio al texto
          borderRadius: 42,
          backgroundColor: '#FFFFFF', // Sólido, nada de transparencias estilo iOS
          borderWidth: 0,
          borderTopWidth: 0,

          // Sombra más profunda y suave para efecto flotante
          elevation: 10,
          shadowColor: '#111827',
          shadowOffset: {
            width: 0,
            height: 10,
          },
          shadowOpacity: 0.1,
          shadowRadius: 12, // Reducimos para mejorar el performance de dibujado
        },

        tabBarItemStyle: { paddingTop: 10, paddingBottom: 12 },
        tabBarIconStyle: { marginBottom: 6 }, // Empuja un poco el texto hacia abajo

        tabBarActiveTintColor: Colors.primary || '#111827',
        tabBarInactiveTintColor: '#9CA3AF',

        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '800',
          marginTop: 0,
        },
      }}
    >
      {TABS.map((tab) => (
        <Tabs.Screen
          key={tab.name}
          name={tab.name}
          options={{
            title: tab.title,
            tabBarLabel: tab.title, // Aseguramos que el label use el título del objeto TABS
            tabBarIcon: (props) => <TabIcon {...props} icon={tab.icon} iconFocused={tab.iconFocused} />,
          }}
        />
      ))}
    </Tabs>
  );
}

const styles = StyleSheet.create({
  iconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    width: 50,
    height: 32,
  },
  activePill: {
    position: 'absolute',
    width: '100%',
    height: '100%',
    borderRadius: 16,
  },
});