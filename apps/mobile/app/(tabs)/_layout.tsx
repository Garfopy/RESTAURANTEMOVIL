import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Platform, View, StyleSheet, Animated, Easing } from 'react-native';
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

// Componente optimizado para transiciones instantáneas sin bloquear el hilo de JS
const TabIcon = memo(({ focused, color, icon, iconFocused }: { focused: boolean; color: string; icon: IoniconName; iconFocused: IoniconName }) => {
  const scaleAnim = useRef(new Animated.Value(focused ? 1.12 : 1)).current;
  const opacityAnim = useRef(new Animated.Value(focused ? 1 : 0)).current;

  useEffect(() => {
    // Usamos paralelas con Timing + Easing para una respuesta instantánea al tacto
    Animated.parallel([
      Animated.timing(scaleAnim, {
        toValue: focused ? 1.12 : 1,
        duration: 180,
        easing: Easing.out(Easing.back(1.5)), // Genera un sutil rebote premium sin la carga del Spring
        useNativeDriver: true,
      }),
      Animated.timing(opacityAnim, {
        toValue: focused ? 1 : 0,
        duration: 140,
        easing: Easing.out(Easing.ease),
        useNativeDriver: true,
      })
    ]).start();
  }, [focused]);

  return (
    <View style={styles.iconContainer} pointerEvents="none">
      {/* Cápsula de fondo activo (Efecto Liquid Pill con opacidad animada) */}
      <Animated.View style={[styles.activePill, { backgroundColor: `${color}10`, opacity: opacityAnim }]} />
      
      <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
        <Ionicons
          name={focused ? iconFocused : icon}
          size={23}
          color={color}
        />
      </Animated.View>
    </View>
  );
});

export default function TabsLayout() {
  return (
    <Tabs
      sceneContainerStyle={{ backgroundColor: Colors.background || '#F9FAFB' }}
      screenOptions={{
        headerShown: false,
        tabBarAllowFontScaling: false,
        tabBarShowLabel: true,
        lazy: true,
        detachInactiveScreens: true,
        // 👇 CONGELA LAS PANTALLAS EN SEGUNDO PLANO (Adiós al lag entre cambios) 👇
        freezeOnBlur: true, 

        tabBarStyle: {
          position: 'absolute',
          left: 20,
          right: 20,
          bottom: Platform.OS === 'ios' ? 28 : 16,
          height: 79,
          borderRadius: 28,
          backgroundColor: '#FFFFFF',
          borderWidth: 0,
          borderTopWidth: 0,
          paddingHorizontal: 8,
          
          // Sombras Pro de alta fidelidad difuminadas (Evita saltos bruscos)
          ...Platform.select({
            ios: {
              shadowColor: '#111827',
              shadowOffset: { width: 0, height: 8 },
              shadowOpacity: 0.06,
              shadowRadius: 16,
            },
            android: {
              elevation: 6,
            }
          })
        },

        tabBarItemStyle: { 
          paddingTop: 12, 
          paddingBottom: 10 
        },
        tabBarIconStyle: { 
          marginBottom: 4 
        },

        tabBarActiveTintColor: Colors.primary || '#111827',
        tabBarInactiveTintColor: '#9CA3AF',

        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '700',
          letterSpacing: -0.1,
          marginTop: 2,
        },
      }}
    >
      {TABS.map((tab) => (
        <Tabs.Screen
          key={tab.name}
          name={tab.name}
          options={{
            title: tab.title,
            tabBarLabel: tab.title,
            tabBarIcon: ({ focused, color }) => (
              <TabIcon 
                focused={focused} 
                color={color} 
                icon={tab.icon} 
                iconFocused={tab.iconFocused} 
              />
            ),
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
    width: 56,
    height: 32,
    position: 'relative',
  },
  activePill: {
    position: 'absolute',
    width: '100%',
    height: '100%',
    borderRadius: 12,
  },
});