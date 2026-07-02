import React from 'react';
import { Tabs } from 'expo-router';
import { StaffTabBar, type StaffTabItem } from '../../components/staff/StaffTabBar';

const TABS: Record<string, StaffTabItem> = {
  index: { label: 'Mesas', icon: 'grid-outline', activeIcon: 'grid' },
  orders: { label: 'Pedidos', icon: 'receipt-outline', activeIcon: 'receipt' },
  gifts: { label: 'Regalos', icon: 'gift-outline', activeIcon: 'gift' },
};

export default function WaiterLayout() {
  return (
    <Tabs
      tabBar={(props) => <StaffTabBar {...props} items={TABS} accent="#111827" />}
      screenOptions={{
        headerShown: false,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Mesas',
        }}
      />
      <Tabs.Screen
        name="orders"
        options={{
          title: 'Pedidos',
        }}
      />
      <Tabs.Screen
        name="gifts"
        options={{
          title: 'Regalos',
        }}
      />
      <Tabs.Screen name="table/[id]" options={{ href: null }} />
    </Tabs>
  );
}
