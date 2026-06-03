import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Platform } from 'react-native';
import { Colors } from '../../theme';

type IoniconName = keyof typeof Ionicons.glyphMap;

const TABS: { name: string; title: string; icon: IoniconName; iconFocused: IoniconName }[] = [
  { name: 'index',      title: 'Inicio',      icon: 'home-outline',           iconFocused: 'home' },
  { name: 'orders',     title: 'Pedidos',     icon: 'bag-outline',            iconFocused: 'bag' },
  { name: 'favorites',  title: 'Favoritos',   icon: 'heart-outline',          iconFocused: 'heart' },
  { name: 'promotions', title: 'Promos',      icon: 'pricetag-outline',       iconFocused: 'pricetag' },
  { name: 'profile',    title: 'Perfil',      icon: 'person-circle-outline',  iconFocused: 'person-circle' },
];

export default function TabsLayout() {
  return (
    <Tabs
      sceneContainerStyle={{ backgroundColor: '#ffffff' }}
      screenOptions={{
        headerShown: false,
        tabBarAllowFontScaling: false,
        tabBarShowLabel: true,

        tabBarStyle: {
          position: 'absolute',
          left: 16,
          right: 16,
          bottom: Platform.OS === 'ios' ? 24 : 16,

          height: 56,
          borderRadius: 24,
          backgroundColor: '#ffffff',

          borderTopWidth: 0,

          elevation: 20,
          shadowColor: '#000',
          shadowOffset: {
            width: 0,
            height: 8,
          },
          shadowOpacity: 0.12,
          shadowRadius: 12,
        },

        tabBarItemStyle: {
          paddingHorizontal: 0,
          marginHorizontal: -4,
        },

        tabBarIconStyle: {
          marginBottom: -2,
        },

        tabBarActiveTintColor: Colors.primary || '#111827',
        tabBarInactiveTintColor: '#9CA3AF',

        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '700',
          marginTop: -2,
        },
      }}
    >
      {TABS.map((tab) => (
        <Tabs.Screen
          key={tab.name}
          name={tab.name}
          options={{
            title: tab.title,
            tabBarIcon: ({ focused, color }) => (
              <Ionicons
                name={focused ? tab.iconFocused : tab.icon}
                size={25}
                color={color}
              />
            ),
          }}
        />
      ))}
    </Tabs>
  );
}