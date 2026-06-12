import { apiClient } from './api';

export interface RemoteThemeSettings {
  primary: string;
  secondary: string;
  background?: string;
  button?: string;
  buttonText?: string;
}

export async function getThemeSettings(): Promise<RemoteThemeSettings> {
  const { data } = await apiClient.get<{
    success: boolean;
    data: { theme: RemoteThemeSettings };
  }>('/settings/theme');

  return data.data.theme;
}
