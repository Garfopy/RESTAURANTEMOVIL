import React from 'react';
import { Stack } from 'expo-router';

export default function WaiterLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="table/[id]" />
    </Stack>
  );
}
