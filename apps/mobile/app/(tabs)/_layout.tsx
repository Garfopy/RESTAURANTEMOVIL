import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../../theme';

type IoniconName = keyof typeof Ionicons.glyphMap;

const TABS: { name: string; title: string; icon: IoniconName; iconFocused: IoniconName }[] = [
  { name: 'index',      title: 'Inicio',      icon: 'home-outline',          iconFocused: 'home' },
  { name: 'orders',     title: 'Pedidos',     icon: 'bag-outline',           iconFocused: 'bag' },
  { name: 'favorites',  title: 'Favoritos',   icon: 'heart-outline',         iconFocused: 'heart' },
  { name: 'promotions', title: 'Promociones', icon: 'pricetag-outline',      iconFocused: 'pricetag' },
  { name: 'profile',    title: 'Perfil',      icon: 'person-circle-outline', iconFocused: 'person-circle' },
];

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: Colors.primary,
          borderTopColor: 'rgba(255,255,255,0.1)',
          height: 80,
          paddingBottom: 16,
          paddingTop: 8,
        },
        tabBarActiveTintColor: Colors.accent,
        tabBarInactiveTintColor: 'rgba(255,255,255,0.5)',
        tabBarLabelStyle: { fontSize: 11, fontWeight: '500' },
      }}
    >
      {TABS.map((tab) => (
        <Tabs.Screen
          key={tab.name}
          name={tab.name}
          options={{
            title: tab.title,
            tabBarIcon: ({ focused, color, size }) => (
              <Ionicons
                name={focused ? tab.iconFocused : tab.icon}
                size={size}
                color={color}
              />
            ),
          }}
        />
      ))}
    </Tabs>
  );
}
