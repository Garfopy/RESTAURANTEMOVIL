import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Linking,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
  useWindowDimensions,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { GestureDetector, Gesture } from 'react-native-gesture-handler';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
  withTiming,
  runOnJS,
} from 'react-native-reanimated';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { LinearGradient } from 'expo-linear-gradient';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import InputField from '../../components/ui/InputField';
import { TableContextBanner } from '../../components/shared/TableContextBanner';
import { STRIPE_IS_CONFIGURED, STRIPE_PUBLISHABLE_KEY } from '../../constants/stripe';
import { apiClient, formatImageUrl, getApiError } from '../../services/api';
import {
  confirmSocialGiftPayment,
  createSocialGiftPayment,
  type GiftCheckoutMode,
  type SocialGiftOrder,
} from '../../services/social-gifts.service';
import {
  confirmSocialAccountCoverPayment,
  coverSocialDinerAccount,
  getSocialAccountNotifications,
  getSocialDinerAccount,
  prepareSocialAccountCoverPayment,
  respondSocialAccountCoverRequest,
  type SocialAccountNotification,
  type SocialDinerAccountResult,
} from '../../services/social-account.service';
import { getRewardsWallet, quoteRewards, type RewardsQuote, type RewardsWallet } from '../../services/rewards.service';
import { Colors, Shadows } from '../../theme';
import { useBranchStore } from '../../store/branch.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useUserStore } from '../../store/user.store';

const GIFT_TERMS_MESSAGE =
  'La persona no esta obligada a recibirlo. El restaurante no se hace responsable de rechazos. El reembolso del 50% aplica solo en productos u objetos; no aplica en comida ni bebidas.';

type SelectOption = {
  label: string;
  value: string;
  description?: string;
};

type MesaApiItem = {
  id: number;
  label: string;
  value: string;
};

type SocialProfileResponse = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos?: string[] | null;
  edad: number | null;
  sexualidad: string | null;
  genero: string | null;
  descripcion: string | null;
  intereses: string | null;
  que_busca: string | null;
  redes_sociales: string | null;
  mesa?: string | null;
  is_social_active?: boolean | null;
  modo_social?: boolean | null;
  current_restaurante_id?: number | null;
  social_updated_at?: string | null;
  social_consent_accepted_at?: string | null;
  social_consent_version?: string | null;
  requires_social_consent?: boolean | null;
  has_social_profile?: boolean;
};

type ApiEnvelope<T> = {
  success?: boolean;
  message?: string;
  data?: T;
};

type SocialPhotoResponse = {
  foto_url?: string | null;
  social_photos?: string[] | null;
  uploaded_photo_url?: string | null;
};

type SocialStatusResponse = {
  is_social_active?: boolean;
  modo_social?: boolean;
  current_restaurante_id?: number | null;
  mesa?: string | null;
  social_consent_accepted_at?: string | null;
  social_consent_version?: string | null;
  requires_social_consent?: boolean | null;
};

type DinerApiItem = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos?: string[] | null;
  edad: number | null;
  genero: string | null;
  sexualidad: string | null;
  descripcion: string | null;
  intereses: string | null;
  que_busca: string | null;
  redes_sociales?: string | null;
  mesa?: string | null;
  relationship_status?: 'none' | 'liked' | 'matched' | string | null;
  matched_at?: string | null;
  match_restaurante_id?: number | null;
  liked_at?: string | null;
  like_restaurante_id?: number | null;
};

type SocialFormState = {
  nombre: string;
  edad: string;
  genero: string;
  sexualidad: string;
  queBusca: string;
  biografia: string;
  instagram: string;
  tiktok: string;
};

type SocialFilterState = {
  edadMin: string;
  edadMax: string;
  genero: string;
  sexualidad: string;
};

type SocialDiner = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos: string[];
  edad: number | null;
  genero: string | null;
  sexualidad: string | null;
  descripcion: string | null;
  intereses: string[];
  que_busca: string | null;
  redes_sociales: string | null;
  mesa: string | null;
  relationship_status: 'none' | 'liked' | 'matched';
  matched_at?: string | null;
  match_restaurante_id?: number | null;
  liked_at?: string | null;
  like_restaurante_id?: number | null;
};

type SocialLikeResponse = {
  liked: boolean;
  matched: boolean;
  relationship_status: 'liked' | 'matched';
  match?: DinerApiItem | null;
};

type SocialUnlikeResponse = {
  liked: false;
  matched: false;
  relationship_status: 'none';
};

type SocialView = 'discover' | 'matches' | 'likes';
type LikesView = 'received' | 'sent';

const SEEN_INCOMING_LIKE_KEY_PREFIX = 'amare_social_seen_incoming_like';

function getSeenIncomingLikeStorageKey(userId: number | string): string {
  return `${SEEN_INCOMING_LIKE_KEY_PREFIX}:${userId}`;
}

function getIncomingLikeKey(diner?: SocialDiner | null): string | null {
  if (!diner?.user_id) {
    return null;
  }

  return `${diner.user_id}:${diner.liked_at ?? ''}`;
}

type GiftProduct = {
  id: number;
  nombre: string;
  descripcion?: string | null;
  precio: number;
  icono?: string | null;
  color?: string | null;
  es_regalo?: boolean;
  imagen?: string | null;
  orden?: number;
  tipo?: string | null;
};

const GENDER_OPTIONS: SelectOption[] = [
  { label: 'Hombre', value: 'Hombre' },
  { label: 'Mujer', value: 'Mujer' },
  { label: 'No binario', value: 'No binario' },
  { label: 'Otro', value: 'Otro' },
  { label: 'Prefiero no decirlo', value: 'Prefiero no decirlo' },
];

const SEXUALITY_OPTIONS: SelectOption[] = [
  {
    label: 'Heterosexual',
    value: 'Heterosexual',
    description: 'Atracción romántica o sexual hacia personas de un género distinto al propio.',
  },
  {
    label: 'Homosexual',
    value: 'Homosexual',
    description: 'Atracción romántica o sexual hacia personas del mismo género.',
  },
  {
    label: 'Bisexual',
    value: 'Bisexual',
    description: 'Atracción romántica o sexual hacia más de un género.',
  },
  {
    label: 'Pansexual',
    value: 'Pansexual',
    description: 'Atracción romántica o sexual hacia personas sin que el género sea el factor principal.',
  },
  {
    label: 'Asexual',
    value: 'Asexual',
    description: 'Poca o nula atracción sexual. Puede existir atracción romántica, afectiva o emocional.',
  },
  {
    label: 'Prefiero no decirlo',
    value: 'Prefiero no decirlo',
    description: 'Puedes mantener esta información privada.',
  },
];

const LOOKING_FOR_OPTIONS: SelectOption[] = [
  { label: 'Amigos', value: 'Amigos' },
  { label: 'Relacion seria', value: 'Relacion seria' },
  { label: 'Nada serio', value: 'Nada serio' },
  { label: 'Conocer gente', value: 'Conocer gente' },
  { label: 'Salir y platicar', value: 'Salir y platicar' },
];

const DEFAULT_INTEREST_OPTIONS = [
  'Coleccionismo',
  'Moda urbana y sneakers',
  'Mascotas',
  'Viajes y turismo',
  'Lectura y literatura',
  'Voluntariado',
  'Teatro',
  'Cerveza artesanal',
  'Videojuegos',
  'Musica',
  'Arte',
  'Cine',
  'Cafe',
  'Comida asiatica',
  'Fitness',
  'Tecnologia',
];

const EMPTY_FORM: SocialFormState = {
  nombre: '',
  edad: '',
  genero: '',
  sexualidad: '',
  queBusca: '',
  biografia: '',
  instagram: '',
  tiktok: '',
};

const EMPTY_FILTERS: SocialFilterState = {
  edadMin: '',
  edadMax: '',
  genero: '',
  sexualidad: '',
};

const SOCIAL_PRIVACY_NOTICE_URL = 'https://amarerestaurant.club/aviso-de-privacidad';

function parseInterestList(value?: string | null): string[] {
  if (!value) return [];
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function parseSocialNetworks(value?: string | null): { instagram: string; tiktok: string } {
  if (!value) return { instagram: '', tiktok: '' };

  try {
    const parsed = JSON.parse(value) as { instagram?: string | null; tiktok?: string | null };
    return {
      instagram: parsed.instagram?.trim() ?? '',
      tiktok: parsed.tiktok?.trim() ?? '',
    };
  } catch {
    const fallback = value.trim();
    return {
      instagram: fallback.startsWith('@') ? fallback : fallback ? `@${fallback}` : '',
      tiktok: '',
    };
  }
}

function stringifySocialNetworks(instagram: string, tiktok: string): string | null {
  const cleanInstagram = instagram.trim();
  const cleanTiktok = tiktok.trim();

  if (!cleanInstagram && !cleanTiktok) return null;

  return JSON.stringify({
    instagram: cleanInstagram || null,
    tiktok: cleanTiktok || null,
  });
}

function isProfileReady(profile: {
  foto_url?: string | null;
  edad?: number | null;
  sexualidad?: string | null;
  genero?: string | null;
  descripcion?: string | null;
}): boolean {
  return Boolean(
    profile.foto_url &&
      profile.edad !== null &&
      profile.edad !== undefined &&
      profile.sexualidad &&
      profile.genero &&
      profile.descripcion?.trim()
  );
}

function resolvePhotoUrl(path?: string | null): string | null {
  if (!path) return null;
  return formatImageUrl(path) ?? path;
}

function normalizePhotoList(value?: (string | null)[] | null, fallback?: string | null): string[] {
  const rawPhotos = Array.isArray(value) ? value : [];
  const photos = rawPhotos
    .map((photo) => resolvePhotoUrl(photo))
    .filter((photo): photo is string => Boolean(photo));
  const fallbackPhoto = resolvePhotoUrl(fallback);

  if (photos.length === 0 && fallbackPhoto) {
    photos.push(fallbackPhoto);
  }

  return Array.from(new Set(photos)).slice(0, 6);
}

function normalizeMesaValue(value: unknown): string | null {
  if (value === null || value === undefined) return null;

  const normalized = String(value).trim();
  return normalized ? normalized : null;
}

function formatHandleLabel(value?: string | null): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  if (!trimmed) return null;
  return trimmed.startsWith('@') ? trimmed : `@${trimmed}`;
}

function formatMatchDate(value?: string | null): string | null {
  if (!value) return null;
  const date = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return null;
  return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function summarizeStripeSecret(secret?: string): string | null {
  if (!secret) return null;
  if (secret.length <= 16) return secret;
  return `${secret.slice(0, 10)}...${secret.slice(-6)}`;
}

function summarizeStripeKey(key?: string): string | null {
  if (!key) return null;
  if (key.length <= 14) return key;
  return `${key.slice(0, 12)}...${key.slice(-4)}`;
}

function unwrapApiData<T>(payload: ApiEnvelope<T> | T): T {
  if (payload && typeof payload === 'object' && 'data' in payload && payload.data !== undefined) {
    return payload.data as T;
  }

  return payload as T;
}

function moduloIndex(index: number, total: number): number {
  if (total <= 0) return 0;
  return ((index % total) + total) % total;
}

function buildDiner(item: DinerApiItem): SocialDiner {
  const socialPhotos = normalizePhotoList(item.social_photos, item.foto_url);
  const relationshipStatus =
    item.relationship_status === 'liked' || item.relationship_status === 'matched'
      ? item.relationship_status
      : 'none';

  return {
    user_id: Number(item.user_id),
    nombre: item.nombre,
    foto_url: socialPhotos[0] ?? resolvePhotoUrl(item.foto_url),
    social_photos: socialPhotos,
    edad: item.edad ?? null,
    genero: item.genero ?? null,
    sexualidad: item.sexualidad ?? null,
    descripcion: item.descripcion ?? null,
    intereses: parseInterestList(item.intereses),
    que_busca: item.que_busca ?? null,
    redes_sociales: item.redes_sociales ?? null,
    mesa: normalizeMesaValue(item.mesa),
    relationship_status: relationshipStatus,
    matched_at: item.matched_at ?? null,
    match_restaurante_id: item.match_restaurante_id ?? null,
    liked_at: item.liked_at ?? null,
    like_restaurante_id: item.like_restaurante_id ?? null,
  };
}

function isDinerApiItem(value: unknown): value is DinerApiItem {
  return Boolean(
    value &&
      typeof value === 'object' &&
      'user_id' in value &&
      'nombre' in value
  );
}

function isSocialDiner(value: unknown): value is SocialDiner {
  return Boolean(
    value &&
      typeof value === 'object' &&
      'user_id' in value &&
      'nombre' in value &&
      Array.isArray((value as SocialDiner).intereses)
  );
}

function isGiftProductItem(value: unknown): value is Record<string, unknown> {
  return Boolean(
    value &&
      typeof value === 'object' &&
      'id' in value &&
      'nombre' in value
  );
}

function normalizeGiftProduct(item: Record<string, unknown>): GiftProduct {
  const price = Number(item.precio ?? 0);
  const image = typeof item.imagen === 'string' ? item.imagen : null;

  return {
    id: Number(item.id),
    nombre: String(item.nombre ?? 'Regalo'),
    descripcion: typeof item.descripcion === 'string' ? item.descripcion : null,
    precio: Number.isFinite(price) ? price : 0,
    icono: typeof item.icono === 'string' ? item.icono : null,
    color: typeof item.color === 'string' ? item.color : Colors.primary,
    es_regalo: item.es_regalo === undefined ? true : Boolean(item.es_regalo),
    imagen: resolvePhotoUrl(image) ?? image,
    orden: Number.isFinite(Number(item.orden)) ? Number(item.orden) : 0,
    tipo: typeof item.tipo === 'string' ? item.tipo : 'gift',
  };
}

function getGiftProductType(gift: GiftProduct): 'gift' | 'menu' {
  return gift.tipo === 'menu' ? 'menu' : 'gift';
}

function ChoiceField({
  label,
  value,
  placeholder,
  options,
  onSelect,
}: {
  label: string;
  value: string;
  placeholder: string;
  options: SelectOption[];
  onSelect: (value: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.value === value);
  const hasDescriptions = options.some((option) => option.description);

  function showOptionInfo() {
    if (selected?.description) {
      Alert.alert(selected.label, selected.description);
      return;
    }

    const descriptionText = options
      .filter((option) => option.description)
      .map((option) => `${option.label}: ${option.description}`)
      .join('\n\n');

    Alert.alert(label, descriptionText || 'Selecciona una opción para ver más información.');
  }

  return (
    <View style={styles.fieldBlock}>
      <View style={styles.fieldLabelRow}>
        <Text style={styles.fieldLabel}>{label}</Text>
        {hasDescriptions ? (
          <TouchableOpacity
            style={styles.fieldInfoButton}
            activeOpacity={0.75}
            onPress={showOptionInfo}
            accessibilityLabel={`Información sobre ${label}`}
            accessibilityRole="button"
          >
            <Ionicons name="information-circle-outline" size={18} color={Colors.primary} />
          </TouchableOpacity>
        ) : null}
      </View>
      <TouchableOpacity
        activeOpacity={0.75}
        onPress={() => setOpen((current) => !current)}
        style={styles.choiceButton}
      >
        <Text style={[styles.choiceValue, !selected && styles.choicePlaceholder]}>
          {selected?.label ?? placeholder}
        </Text>
        <Ionicons name={open ? 'chevron-up' : 'chevron-down'} size={18} color={Colors.textSecondary} />
      </TouchableOpacity>

      {open ? (
        <View style={styles.choiceList}>
          {options.map((option, index) => (
            <TouchableOpacity
              key={option.value}
              activeOpacity={0.75}
              onPress={() => {
                onSelect(option.value);
                setOpen(false);
              }}
              style={[
                styles.choiceItem,
                value === option.value && styles.choiceItemActive,
                index === options.length - 1 && styles.choiceItemLast,
              ]}
            >
              <Text style={[styles.choiceItemText, value === option.value && styles.choiceItemTextActive]}>
                {option.label}
              </Text>
              {value === option.value ? <Ionicons name="checkmark" size={18} color={Colors.primary} /> : null}
            </TouchableOpacity>
          ))}
        </View>
      ) : null}
      {selected?.description ? <Text style={styles.choiceDescription}>{selected.description}</Text> : null}
    </View>
  );
}

function getSexualityDescription(value?: string | null): string | null {
  if (!value) return null;
  return SEXUALITY_OPTIONS.find((option) => option.value === value)?.description ?? null;
}

export default function SocialProfileScreen() {
  const router = useRouter();
  const { activateSocial } = useLocalSearchParams<{ activateSocial?: string }>();
  const { width, height } = useWindowDimensions();
  const { confirmPayment: stripeConfirm } = useStripe();
  const user = useUserStore((state) => state.user);
  const updateProfile = useUserStore((state) => state.updateProfile);
  const selectedBranch = useBranchStore((state) => state.seleccionada);
  const tableSession = useTableSessionStore((state) => state.session);
  const deferredBranch = useTableSessionStore((state) => state.deferredBranch);

  const [form, setForm] = useState<SocialFormState>(EMPTY_FORM);
  const [selectedInterests, setSelectedInterests] = useState<string[]>([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [mesaModalVisible, setMesaModalVisible] = useState(false);
  const [consentModalVisible, setConsentModalVisible] = useState(false);
  const [filtersVisible, setFiltersVisible] = useState(false);
  const [detailsVisible, setDetailsVisible] = useState(false);
  const [giftsVisible, setGiftsVisible] = useState(false);
  const [likeGiftPromptVisible, setLikeGiftPromptVisible] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [statusUpdating, setStatusUpdating] = useState(false);
  const [profileLoading, setProfileLoading] = useState(true);
  const [dinersLoading, setDinersLoading] = useState(false);
  const [matchesLoading, setMatchesLoading] = useState(false);
  const [likesLoading, setLikesLoading] = useState(false);
  const [likingUserId, setLikingUserId] = useState<number | null>(null);
  const [giftProductsLoading, setGiftProductsLoading] = useState(false);
  const [giftSending, setGiftSending] = useState(false);
  const [giftCheckoutMode, setGiftCheckoutMode] = useState<GiftCheckoutMode>('account');
  const [giftCardComplete, setGiftCardComplete] = useState(false);
  const [giftRewardsWallet, setGiftRewardsWallet] = useState<RewardsWallet | null>(null);
  const [giftRewardsQuote, setGiftRewardsQuote] = useState<RewardsQuote | null>(null);
  const [giftRewardsLoading, setGiftRewardsLoading] = useState(false);
  const [useGiftRewardsPoints, setUseGiftRewardsPoints] = useState(false);
  const [coverAccountVisible, setCoverAccountVisible] = useState(false);
  const [coverAccountDiner, setCoverAccountDiner] = useState<SocialDiner | null>(null);
  const [coverAccountResult, setCoverAccountResult] = useState<SocialDinerAccountResult | null>(null);
  const [coverAccountLoading, setCoverAccountLoading] = useState(false);
  const [coverAccountSending, setCoverAccountSending] = useState(false);
  const [coverAccountMode, setCoverAccountMode] = useState<'account' | 'stripe'>('account');
  const [accountNotifications, setAccountNotifications] = useState<SocialAccountNotification[]>([]);
  const [accountNotificationBusyId, setAccountNotificationBusyId] = useState<number | null>(null);
  const [approvedCoverPayment, setApprovedCoverPayment] = useState<SocialAccountNotification | null>(null);
  const [approvedCoverCardComplete, setApprovedCoverCardComplete] = useState(false);
  const [approvedCoverPaying, setApprovedCoverPaying] = useState(false);
  const [mesaOptionsLoading, setMesaOptionsLoading] = useState(false);
  const [modoSocial, setModoSocial] = useState(false);
  const [hasCompleteProfile, setHasCompleteProfile] = useState(false);
  const [requiresSocialConsent, setRequiresSocialConsent] = useState(false);
  const [socialConsentChecked, setSocialConsentChecked] = useState(false);
  const [socialPhotos, setSocialPhotos] = useState<string[]>([]);
  const [diners, setDiners] = useState<SocialDiner[]>([]);
  const [matches, setMatches] = useState<SocialDiner[]>([]);
  const [receivedLikes, setReceivedLikes] = useState<SocialDiner[]>([]);
  const [sentLikes, setSentLikes] = useState<SocialDiner[]>([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [detailPhotoIndex, setDetailPhotoIndex] = useState(0);
  const [dinerDetails, setDinerDetails] = useState<Record<number, SocialDiner>>({});
  const [focusedDiner, setFocusedDiner] = useState<SocialDiner | null>(null);
  const [likeGiftPromptDiner, setLikeGiftPromptDiner] = useState<SocialDiner | null>(null);
  const [likeGiftPromptMatched, setLikeGiftPromptMatched] = useState(false);
  const [giftProducts, setGiftProducts] = useState<GiftProduct[]>([]);
  const [giftProductsRestaurantId, setGiftProductsRestaurantId] = useState<number | null>(null);
  const [selectedGiftId, setSelectedGiftId] = useState<number | null>(null);
  const [filters, setFilters] = useState<SocialFilterState>(EMPTY_FILTERS);
  const [mesaInput, setMesaInput] = useState('');
  const [mesaOptions, setMesaOptions] = useState<SelectOption[]>([]);
  const [pendingActivationAfterBranch, setPendingActivationAfterBranch] = useState(false);
  const [pendingSocialMesaValue, setPendingSocialMesaValue] = useState<string | null>(null);
  const [socialView, setSocialView] = useState<SocialView>('discover');
  const [likesView, setLikesView] = useState<LikesView>('received');
  const [floatingLikeVisible, setFloatingLikeVisible] = useState(false);
  const [seenIncomingLikeKey, setSeenIncomingLikeKey] = useState<string | null | undefined>(undefined);
  const userSocialActive = Boolean(user?.is_social_active || user?.modo_social);

  const translateX = useSharedValue(0);
  const translateY = useSharedValue(0);
  const rotate = useSharedValue(0);
  const detailPhotoScrollRef = useRef<ScrollView | null>(null);
  const giftRequestKeyRef = useRef<string | null>(null);
  const socialScanActivationHandledRef = useRef(false);
  const staleSocialActivationHandledRef = useRef(false);

  const interestOptions = useMemo(() => {
    const unique = new Set(DEFAULT_INTEREST_OPTIONS);
    selectedInterests.forEach((interest) => unique.add(interest));
    return Array.from(unique);
  }, [selectedInterests]);
  const activeFilterCount = useMemo(
    () => [filters.edadMin, filters.edadMax, filters.genero, filters.sexualidad].filter(Boolean).length,
    [filters]
  );

  const SWIPE_THRESHOLD = width * 0.35;

  const safeCurrentIndex = diners.length > 0 ? moduloIndex(currentIndex, diners.length) : 0;
  const currentDinerBase = diners[safeCurrentIndex] ?? null;
  const currentDinerDetail = currentDinerBase ? dinerDetails[currentDinerBase.user_id] : undefined;
  const currentDiner =
    currentDinerBase && currentDinerDetail
      ? {
          ...currentDinerBase,
          ...currentDinerDetail,
        }
      : currentDinerBase;
  const detailDiner = focusedDiner ?? currentDiner;
  const selectedGift = giftProducts.find((item) => item.id === selectedGiftId) ?? null;
  const stripeAvailable = STRIPE_IS_CONFIGURED;
  const canSubmitGift = Boolean(selectedGift) &&
    !giftProductsLoading &&
    !giftSending &&
    (giftCheckoutMode === 'account' ||
      (giftCheckoutMode === 'wallet' && Boolean(giftRewardsQuote?.can_pay) && !giftRewardsLoading) ||
      (giftCheckoutMode === 'stripe' && stripeAvailable && giftCardComplete));
  const detailPhotos = detailDiner
    ? normalizePhotoList(detailDiner.social_photos, detailDiner.foto_url)
    : [];
  const detailImageWidth = Math.max(260, width - 36);
  const isCompactDiscovery = height < 760 || width < 380;
  const topReceivedLike = receivedLikes[0] ?? null;
  const latestAccountNotification = accountNotifications[0] ?? null;
  const latestAccountExitPass = getNotificationExitPass(latestAccountNotification);
  const latestAccountCoverId = getNotificationNumberPayload(latestAccountNotification, 'cover_id');
  const latestAccountPaymentMode = getNotificationStringPayload(latestAccountNotification, 'payment_mode');
  const latestAccountIsRequest = latestAccountNotification?.type === 'social_account_cover_request' && latestAccountCoverId !== null;
  const latestAccountIsApprovedStripe =
    latestAccountNotification?.type === 'social_account_cover_approved' &&
    latestAccountPaymentMode === 'stripe' &&
    latestAccountCoverId !== null;
  const latestAccountIsActionable = latestAccountIsRequest || latestAccountIsApprovedStripe || Boolean(latestAccountExitPass);
  const topReceivedLikeKey = getIncomingLikeKey(topReceivedLike);
  const receivedLikeTitle = topReceivedLike
    ? receivedLikes.length > 1
      ? `${topReceivedLike.nombre} y ${receivedLikes.length - 1} más te dieron me gusta`
      : `${topReceivedLike.nombre} te dio me gusta`
    : '';
  const receivedLikeSubtitle = receivedLikes.length > 1 ? 'Toca para ver todos los perfiles' : 'Toca para ver el perfil';
  const canDiscover = Boolean(modoSocial && selectedBranch?.id);
  const canUseSocialActions = canDiscover;

  useEffect(() => {
    if (!stripeAvailable && giftCheckoutMode === 'stripe') {
      setGiftCheckoutMode('account');
    }
  }, [giftCheckoutMode, stripeAvailable]);

  useEffect(() => {
    let cancelled = false;
    async function loadGiftRewards() {
      if (!giftsVisible || !selectedGift) {
        setGiftRewardsQuote(null);
        return;
      }
      setGiftRewardsLoading(true);
      try {
        const [wallet, quote] = await Promise.all([
          getRewardsWallet(),
          quoteRewards({ context: 'gift', amount: selectedGift.precio, use_points: useGiftRewardsPoints }),
        ]);
        if (!cancelled) {
          setGiftRewardsWallet(wallet);
          setGiftRewardsQuote(quote);
        }
      } catch (error) {
        console.warn('No se pudo cotizar regalo con Saldo Amare', error);
        if (!cancelled) setGiftRewardsQuote(null);
      } finally {
        if (!cancelled) setGiftRewardsLoading(false);
      }
    }
    void loadGiftRewards();
    return () => {
      cancelled = true;
    };
  }, [giftsVisible, selectedGift?.id, selectedGift?.precio, useGiftRewardsPoints]);

  useEffect(() => {
    if (!canDiscover && socialView === 'discover') {
      setSocialView('matches');
    }
  }, [canDiscover, socialView]);

  useEffect(() => {
    let mounted = true;
    setSeenIncomingLikeKey(undefined);
    setFloatingLikeVisible(false);

    if (!user?.id) {
      setSeenIncomingLikeKey(null);
      return () => {
        mounted = false;
      };
    }

    AsyncStorage.getItem(getSeenIncomingLikeStorageKey(user.id))
      .then((value) => {
        if (mounted) {
          setSeenIncomingLikeKey(value);
        }
      })
      .catch(() => {
        if (mounted) {
          setSeenIncomingLikeKey(null);
        }
      });

    return () => {
      mounted = false;
    };
  }, [user?.id]);

  useEffect(() => {
    if (!topReceivedLikeKey || seenIncomingLikeKey === undefined || seenIncomingLikeKey === topReceivedLikeKey) {
      setFloatingLikeVisible(false);
      return undefined;
    }

    setFloatingLikeVisible(true);
    const timer = setTimeout(() => setFloatingLikeVisible(false), 5000);
    return () => clearTimeout(timer);
  }, [seenIncomingLikeKey, topReceivedLikeKey]);

  useEffect(() => {
    if (
      socialView === 'likes' &&
      likesView === 'received' &&
      topReceivedLikeKey &&
      seenIncomingLikeKey !== undefined &&
      seenIncomingLikeKey !== topReceivedLikeKey
    ) {
      setSeenIncomingLikeKey(topReceivedLikeKey);
      setFloatingLikeVisible(false);
      if (user?.id) {
        AsyncStorage.setItem(getSeenIncomingLikeStorageKey(user.id), topReceivedLikeKey).catch(() => undefined);
      }
    }
  }, [likesView, seenIncomingLikeKey, socialView, topReceivedLikeKey, user?.id]);

  function advanceToDiner(direction: 'next' | 'prev') {
    if (diners.length <= 1) {
      return;
    }

    const offset = direction === 'next' ? 1 : -1;
    setCurrentIndex((previous) => moduloIndex(previous + offset, diners.length));
  }

  function isLikedOrMatched(diner: SocialDiner): boolean {
    return diner.relationship_status === 'liked' || diner.relationship_status === 'matched';
  }

  function scrollDetailPhoto(direction: 'next' | 'prev') {
    if (detailPhotos.length <= 1) return;

    const offset = direction === 'next' ? 1 : -1;
    const nextIndex = moduloIndex(detailPhotoIndex + offset, detailPhotos.length);
    setDetailPhotoIndex(nextIndex);
    detailPhotoScrollRef.current?.scrollTo({ x: nextIndex * detailImageWidth, animated: true });
  }

  function handleDetailPhotoScroll(event: NativeSyntheticEvent<NativeScrollEvent>) {
    const x = event.nativeEvent.contentOffset.x;
    const nextIndex = Math.round(x / detailImageWidth);
    if (nextIndex !== detailPhotoIndex) {
      setDetailPhotoIndex(nextIndex);
    }
  }

  function updateDinerRelationship(
    userId: number,
    relationshipStatus: SocialDiner['relationship_status'],
    nextDiner?: SocialDiner
  ) {
    const applyStatus = (diner: SocialDiner): SocialDiner =>
      diner.user_id === userId
        ? {
            ...diner,
            ...nextDiner,
            relationship_status: relationshipStatus,
          }
        : diner;

    setDiners((current) => current.map(applyStatus));
    setDinerDetails((current) => {
      if (!current[userId]) {
        return current;
      }

      return {
        ...current,
        [userId]: applyStatus(current[userId]),
      };
    });
    setFocusedDiner((current) => (current?.user_id === userId ? applyStatus(current) : current));
  }

  const animatedCardStyle = useAnimatedStyle(() => {
    return {
      transform: [
        { translateX: translateX.value },
        { translateY: translateY.value },
        { rotate: `${rotate.value}deg` },
      ],
    };
  });

  const panGesture = Gesture.Pan()
    .onUpdate((event) => {
      translateX.value = event.translationX;
      translateY.value = event.translationY;
      rotate.value = (event.translationX / width) * 15;
    })
    .onEnd((event) => {
      if (Math.abs(event.translationX) > SWIPE_THRESHOLD) {
        const swipeDirection = event.translationX > 0 ? 'prev' : 'next';
        const targetX = event.translationX > 0 ? width + 100 : -width - 100;
        translateX.value = withTiming(targetX, { duration: 250 });
        translateY.value = withTiming(event.translationY, { duration: 250 });
        rotate.value = withTiming(event.translationX > 0 ? 20 : -20, { duration: 250 }, () => {
          runOnJS(handleSwipeDiner)(swipeDirection);
          translateX.value = 0;
          translateY.value = 0;
          rotate.value = 0;
        });
      } else {
        translateX.value = withSpring(0, { damping: 15, stiffness: 150 });
        translateY.value = withSpring(0, { damping: 15, stiffness: 150 });
        rotate.value = withSpring(0, { damping: 15, stiffness: 150 });
      }
    });

  useEffect(() => {
    setDetailPhotoIndex(0);
    detailPhotoScrollRef.current?.scrollTo({ x: 0, animated: false });
  }, [detailDiner?.user_id]);

  useEffect(() => {
    setModoSocial(userSocialActive);

    if (!userSocialActive) {
      setDiners([]);
      setCurrentIndex(0);
      setDinerDetails({});
      setConsentModalVisible(false);
      setPendingSocialMesaValue(null);
    }

    if (typeof user?.requires_social_consent === 'boolean') {
      setRequiresSocialConsent(user.requires_social_consent);
    }

    const nextMesa = normalizeMesaValue(user?.mesa);
    if (nextMesa !== null) {
      setMesaInput(nextMesa);
    }
  }, [userSocialActive, user?.requires_social_consent, user?.mesa]);

  useEffect(() => {
    if (!modoSocial) {
      setAccountNotifications([]);
      return;
    }

    void refreshAccountNotifications();
  }, [modoSocial]);

  useEffect(() => {
    if (activateSocial !== '1' || socialScanActivationHandledRef.current || !tableSession?.mesaValue || modoSocial) {
      return;
    }

    socialScanActivationHandledRef.current = true;
    void requestSocialActivation(tableSession.mesaValue);
  }, [activateSocial, tableSession?.mesaValue, modoSocial]);

  useEffect(() => {
    if (
      profileLoading ||
      staleSocialActivationHandledRef.current ||
      !userSocialActive ||
      tableSession?.mesaValue
    ) {
      return;
    }

    staleSocialActivationHandledRef.current = true;
    void (async () => {
      await persistSocialStatus(false, null);
    })();
  }, [profileLoading, userSocialActive, tableSession?.mesaValue]);

  useEffect(() => {
    let mounted = true;

    async function loadProfile() {
      try {
        setProfileLoading(true);
        const response = await apiClient.get<ApiEnvelope<SocialProfileResponse> | SocialProfileResponse>(
          '/users/social-profile'
        );
        const data = unwrapApiData(response.data);
        if (!mounted) return;

        const redes = parseSocialNetworks(data.redes_sociales);
        const photos = normalizePhotoList(data.social_photos, data.foto_url ?? user?.foto_url ?? null);
        const photoUrl = photos[0] ?? resolvePhotoUrl(data.foto_url) ?? user?.foto_url ?? null;
        const socialEnabled = Boolean(data.is_social_active ?? data.modo_social ?? user?.is_social_active ?? user?.modo_social);
        const mesaValue = normalizeMesaValue(data.mesa) ?? '';

        setForm({
          nombre: data.nombre ?? user?.nombre ?? '',
          edad: data.edad?.toString() ?? '',
          genero: data.genero ?? '',
          sexualidad: data.sexualidad ?? '',
          queBusca: data.que_busca ?? '',
          biografia: data.descripcion ?? '',
          instagram: redes.instagram,
          tiktok: redes.tiktok,
        });
        setSelectedInterests(parseInterestList(data.intereses));
        setSocialPhotos(photos);
        setMesaInput(mesaValue);
        setModoSocial(socialEnabled);
        setRequiresSocialConsent(Boolean(data.requires_social_consent));
        setHasCompleteProfile(
          data.has_social_profile ??
            isProfileReady({
              foto_url: photoUrl,
              edad: data.edad,
              sexualidad: data.sexualidad,
              genero: data.genero,
              descripcion: data.descripcion,
            })
        );

        updateProfile({
          nombre: data.nombre ?? user?.nombre ?? '',
          foto_url: photoUrl,
          social_photos: photos,
          edad: data.edad,
          genero: data.genero,
          sexualidad: data.sexualidad,
          gustos: data.intereses,
          biografia: data.descripcion,
          que_busca: data.que_busca,
          redes_sociales: data.redes_sociales,
          instagram: redes.instagram || null,
          tiktok: redes.tiktok || null,
          is_social_active: socialEnabled,
          modo_social: socialEnabled,
          current_restaurante_id: data.current_restaurante_id ?? null,
          mesa: normalizeMesaValue(data.mesa),
          social_consent_accepted_at: data.social_consent_accepted_at ?? null,
          social_consent_version: data.social_consent_version ?? null,
          requires_social_consent: Boolean(data.requires_social_consent),
        });
      } catch (error) {
        if (mounted) {
          Alert.alert('Perfil social', getApiError(error));
        }
      } finally {
        if (mounted) {
          setProfileLoading(false);
        }
      }
    }

    loadProfile();

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!pendingActivationAfterBranch || !selectedBranch?.id) {
      return;
    }

    const timer = setTimeout(() => {
      setPendingActivationAfterBranch(false);
      void openMesaPrompt();
    }, 220);

    return () => clearTimeout(timer);
  }, [pendingActivationAfterBranch, selectedBranch?.id]);

  useEffect(() => {
    let mounted = true;

    async function loadActiveDiners() {
      if (!modoSocial || !selectedBranch?.id) {
        setDiners([]);
        setCurrentIndex(0);
        return;
      }

      try {
        setDinersLoading(true);
        const requestParams: Record<string, string | number> = {};

        if (filters.edadMin) {
          requestParams.edad_min = Number(filters.edadMin);
        }
        if (filters.edadMax) {
          requestParams.edad_max = Number(filters.edadMax);
        }
        if (filters.genero) {
          requestParams.genero = filters.genero;
        }
        if (filters.sexualidad) {
          requestParams.sexualidad = filters.sexualidad;
        }

        let response;
        try {
          response = await apiClient.get<{ success?: boolean; data?: DinerApiItem[] }>(
            `/restaurants/${selectedBranch.id}/active-diners`,
            { params: requestParams }
          );
        } catch (error) {
          response = await apiClient.get<{ success?: boolean; data?: DinerApiItem[] }>(
            `/restaurants/${selectedBranch.id}/active-users`,
            { params: requestParams }
          );
        }

        if (!mounted) return;

        const rawItems = Array.isArray(response.data)
          ? response.data
          : Array.isArray(response.data?.data)
            ? response.data.data
            : [];

        const nextDiners = rawItems.map(buildDiner);
        setDiners(nextDiners);
        setCurrentIndex(0);
      } catch (error) {
        if (mounted) {
          setDiners([]);
          Alert.alert('Comensales', getApiError(error));
        }
      } finally {
        if (mounted) {
          setDinersLoading(false);
        }
      }
    }

    loadActiveDiners();

    return () => {
      mounted = false;
    };
  }, [filters, modoSocial, selectedBranch?.id]);

  useEffect(() => {
    let mounted = true;

    async function loadCurrentDinerDetails() {
      if (!modoSocial || !currentDiner || dinerDetails[currentDiner.user_id]) return;

      try {
        const response = await apiClient.get<{ success?: boolean; data?: DinerApiItem } | DinerApiItem>(
          `/users/${currentDiner.user_id}/public-profile`
        );
        if (!mounted) return;

        const rawProfile =
          response.data && typeof response.data === 'object' && 'data' in response.data
            ? response.data.data
            : response.data;

        if (!isDinerApiItem(rawProfile)) {
          return;
        }

        setDinerDetails((current) => ({
          ...current,
          [currentDiner.user_id]: buildDiner(rawProfile),
        }));
      } catch {
        // The base diner data is enough to keep the UI working.
      }
    }

    loadCurrentDinerDetails();

    return () => {
      mounted = false;
    };
  }, [currentDiner?.user_id, modoSocial]);

  async function refreshMatches(showLoading = false) {
    try {
      if (showLoading) {
        setMatchesLoading(true);
      }
      const response = await apiClient.get<ApiEnvelope<{ matches: DinerApiItem[] }> | { matches: DinerApiItem[] }>(
        '/social/matches',
        { _suppressConsoleError: true } as any
      );
      const data = unwrapApiData(response.data);
      const rawMatches = Array.isArray(data.matches) ? data.matches : [];
      setMatches(rawMatches.map(buildDiner));
    } catch {
      if (showLoading) {
        setMatches([]);
      }
    } finally {
      if (showLoading) {
        setMatchesLoading(false);
      }
    }
  }

  async function refreshAccountNotifications() {
    try {
      const notifications = await getSocialAccountNotifications();
      setAccountNotifications(notifications);
    } catch {
      setAccountNotifications([]);
    }
  }

  async function refreshReceivedLikes(trackLoading = false) {
    try {
      if (trackLoading) {
        setLikesLoading(true);
      }
      const response = await apiClient.get<ApiEnvelope<{ likes: DinerApiItem[] }> | { likes: DinerApiItem[] }>(
        '/social/likes/received',
        { _suppressConsoleError: true } as any
      );
      const data = unwrapApiData(response.data);
      const rawLikes = Array.isArray(data.likes) ? data.likes : [];
      setReceivedLikes(rawLikes.map(buildDiner));
    } catch {
      // Best effort: older API deployments simply won't show the notification card.
    } finally {
      if (trackLoading) {
        setLikesLoading(false);
      }
    }
  }

  async function refreshSentLikes(trackLoading = false) {
    try {
      if (trackLoading) {
        setLikesLoading(true);
      }
      const response = await apiClient.get<ApiEnvelope<{ likes: DinerApiItem[] }> | { likes: DinerApiItem[] }>(
        '/social/likes/sent',
        { _suppressConsoleError: true } as any
      );
      const data = unwrapApiData(response.data);
      const rawLikes = Array.isArray(data.likes) ? data.likes : [];
      setSentLikes(rawLikes.map(buildDiner));
    } catch {
      if (trackLoading) {
        setSentLikes([]);
      }
    } finally {
      if (trackLoading) {
        setLikesLoading(false);
      }
    }
  }

  useEffect(() => {
    if (!user?.id) {
      return undefined;
    }

    void refreshMatches(true);
    void Promise.all([refreshReceivedLikes(true), refreshSentLikes(false)]);

    const refreshTimer = setInterval(() => {
      void refreshMatches(false);
      void refreshReceivedLikes();
      void refreshSentLikes();
    }, 7000);

    return () => clearInterval(refreshTimer);
  }, [user?.id]);

  function updateField<K extends keyof SocialFormState>(field: K, value: SocialFormState[K]) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function updateFilter<K extends keyof SocialFilterState>(field: K, value: SocialFilterState[K]) {
    setFilters((current) => ({ ...current, [field]: value }));
  }

  function toggleInterest(interest: string) {
    setSelectedInterests((current) =>
      current.includes(interest) ? current.filter((item) => item !== interest) : [...current, interest]
    );
  }

  function handleDinerCardPress(diner?: SocialDiner | null) {
    setFocusedDiner(diner ?? null);
    setDetailsVisible(true);
  }

  function markIncomingLikeSeen(key = topReceivedLikeKey) {
    if (!user?.id || !key) {
      setFloatingLikeVisible(false);
      return;
    }

    setSeenIncomingLikeKey(key);
    setFloatingLikeVisible(false);
    AsyncStorage.setItem(getSeenIncomingLikeStorageKey(user.id), key).catch(() => undefined);
  }

  function handleOpenLikesView(view: LikesView = 'received') {
    if (view === 'received') {
      markIncomingLikeSeen();
    }
    setLikesView(view);
    setSocialView('likes');
    setFloatingLikeVisible(false);
  }

  function handleLikeListPress(diner: SocialDiner) {
    handleDinerCardPress(diner);
  }

  function handleCloseDinerDetails() {
    setDetailsVisible(false);
    setFocusedDiner(null);
  }

  function closeCoverAccountModal() {
    setCoverAccountVisible(false);
    setCoverAccountDiner(null);
    setCoverAccountResult(null);
    setCoverAccountLoading(false);
    setCoverAccountSending(false);
    setCoverAccountMode('account');
  }

  function getNotificationExitPass(notification?: SocialAccountNotification | null): any | null {
    const exitPass = notification?.payload?.exit_pass;
    if (!exitPass || typeof exitPass !== 'object') return null;
    if (!('payload' in exitPass) || !('pedido_id' in exitPass)) return null;
    return exitPass;
  }

  function getNotificationNumberPayload(notification: SocialAccountNotification | null | undefined, key: string): number | null {
    const value = notification?.payload?.[key];
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    if (typeof value === 'string' && value.trim() !== '') {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    }
    return null;
  }

  function getNotificationStringPayload(notification: SocialAccountNotification | null | undefined, key: string): string | null {
    const value = notification?.payload?.[key];
    return typeof value === 'string' ? value : null;
  }

  function handleAccountNotificationPress() {
    if (latestAccountIsApprovedStripe) {
      setApprovedCoverPayment(latestAccountNotification);
      setApprovedCoverCardComplete(false);
      return;
    }
    if (!latestAccountExitPass) return;

    router.push({
      pathname: '/checkout/exit-pass',
      params: {
        orderId: String(latestAccountExitPass.pedido_id),
        payload: String(latestAccountExitPass.payload ?? ''),
        folio: String(latestAccountExitPass.folio ?? ''),
        mesaLabel: String(latestAccountNotification?.payload?.covered_mesa ?? latestAccountExitPass.mesa_id ?? ''),
      },
    } as never);
  }

  async function handleRespondAccountCoverRequest(action: 'accept' | 'reject') {
    if (!latestAccountCoverId || accountNotificationBusyId !== null) return;

    setAccountNotificationBusyId(latestAccountCoverId);
    try {
      const result = await respondSocialAccountCoverRequest(latestAccountCoverId, action);
      if (action === 'accept') {
        Alert.alert(
          'Solicitud aceptada',
          result.cover?.payment_mode === 'stripe'
            ? 'Le avisamos para que termine el pago con tarjeta.'
            : 'Tu cuenta fue cubierta. Ya puedes mostrar tu QR de salida cuando corresponda.'
        );
      } else {
        Alert.alert('Solicitud rechazada', 'Le avisamos que preferiste conservar tu cuenta.');
      }
      await refreshAccountNotifications();
    } catch (error) {
      Alert.alert('No se pudo responder', getApiError(error));
    } finally {
      setAccountNotificationBusyId(null);
    }
  }

  async function handleApprovedCoverStripePayment() {
    const notification = approvedCoverPayment;
    const coverId = getNotificationNumberPayload(notification, 'cover_id');
    if (!notification || !coverId || approvedCoverPaying) return;
    if (!stripeAvailable) {
      Alert.alert('Stripe no disponible', 'La app no tiene Stripe configurado para pagar ahora.');
      return;
    }
    if (!approvedCoverCardComplete) {
      Alert.alert('Tarjeta incompleta', 'Captura una tarjeta valida para pagar la cuenta.');
      return;
    }

    setApprovedCoverPaying(true);
    try {
      const prepared = await prepareSocialAccountCoverPayment(coverId);
      if (!prepared.client_secret || !prepared.cover?.id) {
        throw new Error('No se recibio el cliente de pago de Stripe.');
      }
      const { error } = await stripeConfirm(prepared.client_secret, {
        paymentMethodType: 'Card',
      });
      if (error) {
        Alert.alert('Pago rechazado', error.message);
        return;
      }
      const confirmation = await confirmSocialAccountCoverPayment(prepared.cover.id);
      Alert.alert('Cuenta pagada', confirmation.cover?.message || 'Pagaste la cuenta. Le avisamos al comensal.');
      setApprovedCoverPayment(null);
      setApprovedCoverCardComplete(false);
      await refreshAccountNotifications();
    } catch (error) {
      Alert.alert('No se pudo pagar', getApiError(error));
    } finally {
      setApprovedCoverPaying(false);
    }
  }

  async function openCoverAccountModal(diner?: SocialDiner | null) {
    const target = diner ?? detailDiner;
    if (!target) return;
    if (!selectedBranch?.id) {
      Alert.alert('Sucursal requerida', 'Activa el modo social en una sucursal para cubrir la cuenta de otro comensal.');
      return;
    }

    setCoverAccountDiner(target);
    setCoverAccountVisible(true);
    setCoverAccountLoading(true);
    setCoverAccountResult(null);
    setCoverAccountMode('account');

    try {
      const result = await getSocialDinerAccount(target.user_id, selectedBranch.id);
      setCoverAccountResult(result);
    } catch (error) {
      Alert.alert('No se pudo consultar la cuenta', getApiError(error));
      closeCoverAccountModal();
    } finally {
      setCoverAccountLoading(false);
    }
  }

  function buildCoverRequestKey(dinerUserId: number, mode: 'account' | 'stripe'): string {
    return `cover_${user?.id ?? 'u'}_${dinerUserId}_${mode}_${Date.now().toString(36)}`;
  }

  async function handleCoverAccountSubmit(mode: 'account' | 'stripe') {
    const target = coverAccountDiner;
    if (!target || !selectedBranch?.id || !coverAccountResult?.account) return;

    if (mode === 'stripe' && !stripeAvailable) {
      Alert.alert('Stripe no disponible', 'La app no tiene Stripe configurado para pagar ahora.');
      return;
    }

    setCoverAccountSending(true);
    try {
      await coverSocialDinerAccount({
        dinerUserId: target.user_id,
        restaurantId: selectedBranch.id,
        paymentMode: mode,
        requestKey: buildCoverRequestKey(target.user_id, mode),
      });

      Alert.alert(
        'Solicitud enviada',
        mode === 'stripe'
          ? `${target.nombre} debe aceptar antes de que puedas pagar con tarjeta.`
          : `${target.nombre} debe aceptar antes de que su consumo se agregue a tu cuenta.`
      );
      await refreshAccountNotifications();
      closeCoverAccountModal();
    } catch (error) {
      Alert.alert('No se pudo cubrir la cuenta', getApiError(error));
    } finally {
      setCoverAccountSending(false);
    }
  }

  function handleNextDiner() {
    advanceToDiner('next');
  }

  function handleSwipeDiner(direction: 'next' | 'prev') {
    advanceToDiner(direction);
  }

  async function handleLikeDiner(userId: number) {
    if (likingUserId !== null) return;

    const diner = diners.find((item) => item.user_id === userId) ?? currentDiner;
    if (!diner) return;

    if (!selectedBranch?.id) {
      Alert.alert('Visítanos en una sucursal', 'Visítanos en alguna sucursal para conocer a los comensales.');
      return;
    }

    try {
      setLikingUserId(userId);
      const response = await apiClient.post<ApiEnvelope<SocialLikeResponse> | SocialLikeResponse>('/social/likes', {
        liked_user_id: userId,
        restaurant_id: selectedBranch.id,
      });
      const result = unwrapApiData(response.data);
      const matchedDiner = result.match ? buildDiner(result.match) : { ...diner, relationship_status: result.relationship_status };

      updateDinerRelationship(userId, result.relationship_status, matchedDiner);
      setReceivedLikes((current) => current.filter((item) => item.user_id !== userId));

      if (result.matched) {
        setMatches((current) => {
          const withoutDuplicate = current.filter((item) => item.user_id !== matchedDiner.user_id);
          return [{ ...matchedDiner, relationship_status: 'matched' }, ...withoutDuplicate];
        });
      }
      setLikeGiftPromptDiner(matchedDiner);
      setLikeGiftPromptMatched(Boolean(result.matched));
      setLikeGiftPromptVisible(true);
      void refreshMatches(false);
      void refreshReceivedLikes();
      void refreshSentLikes();
    } catch (error) {
      Alert.alert('No se pudo enviar el like', getApiError(error));
    } finally {
      setLikingUserId(null);
    }
  }

  async function handleUnlikeDiner(userId: number) {
    if (likingUserId !== null) return;

    const diner = diners.find((item) => item.user_id === userId) ?? currentDiner;
    if (!diner) return;

    try {
      setLikingUserId(userId);
      const response = await apiClient.delete<ApiEnvelope<SocialUnlikeResponse> | SocialUnlikeResponse>(`/social/likes/${userId}`);
      const result = unwrapApiData(response.data);
      updateDinerRelationship(userId, result.relationship_status, { ...diner, relationship_status: 'none' });
      setSentLikes((current) => current.filter((item) => item.user_id !== userId));
      void refreshMatches(false);
      void refreshReceivedLikes();
      void refreshSentLikes();
    } catch (error) {
      Alert.alert('No se pudo quitar el like', getApiError(error));
    } finally {
      setLikingUserId(null);
    }
  }

  function applyPhotoResponse(payload: SocialPhotoResponse) {
    const hasGalleryPayload = Array.isArray(payload.social_photos);
    const photos = normalizePhotoList(
      hasGalleryPayload ? payload.social_photos : undefined,
      hasGalleryPayload ? payload.foto_url ?? null : payload.foto_url ?? user?.foto_url ?? null
    );
    const photoUrl = photos[0] ?? resolvePhotoUrl(payload.foto_url) ?? null;

    setSocialPhotos(photos);
    updateProfile({ foto_url: photoUrl, social_photos: photos });
    setHasCompleteProfile((current) =>
      isProfileReady({
        foto_url: photoUrl,
        edad: Number(form.edad) || null,
        sexualidad: form.sexualidad,
        genero: form.genero,
        descripcion: form.biografia,
      }) || current
    );
  }

  async function handlePickImage() {
    if (socialPhotos.length >= 6) {
      Alert.alert('Galería completa', 'Puedes tener hasta 6 fotos en tu perfil social.');
      return;
    }

    Alert.alert('Agregar foto', 'Usa una foto clara donde se vea tu rostro o cuerpo para evitar confusiones.', [
      { text: 'Camara', onPress: () => openPicker(true) },
      { text: 'Galería', onPress: () => openPicker(false) },
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }

  async function openPicker(isCamera: boolean) {
    const remainingSlots = Math.max(0, 6 - socialPhotos.length);
    if (remainingSlots <= 0) {
      Alert.alert('Galería completa', 'Puedes tener hasta 6 fotos en tu perfil social.');
      return;
    }

    const permissionResult = isCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permissionResult.granted) {
      Alert.alert('Permiso requerido', 'Necesitamos acceso para cambiar tu foto.');
      return;
    }

    const result = isCamera
      ? await ImagePicker.launchCameraAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.55 })
      : await ImagePicker.launchImageLibraryAsync({
          allowsEditing: remainingSlots === 1,
          allowsMultipleSelection: remainingSlots > 1,
          selectionLimit: remainingSlots,
          orderedSelection: true,
          aspect: [1, 1],
          quality: 0.55,
        });

    if (result.canceled) {
      return;
    }

    const selectedUris = result.assets
      .map((asset) => asset.uri)
      .filter((uri): uri is string => Boolean(uri))
      .slice(0, remainingSlots);

    if (selectedUris.length > 0) {
      await uploadAvatars(selectedUris);
    }
  }

  async function uploadSingleSocialPhoto(uri: string): Promise<SocialPhotoResponse> {
    const formData = new FormData();
    const filename = uri.split('/').pop() || 'avatar.jpg';
    const extension = /\.(\w+)$/.exec(filename)?.[1]?.toLowerCase() ?? 'jpg';
    const type = extension === 'jpg' ? 'image/jpeg' : `image/${extension}`;

    formData.append('photo', { uri, name: filename, type } as never);

    const response = await apiClient.post<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>('/users/social-profile/photo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 60000,
    });

    return unwrapApiData(response.data);
  }

  async function uploadAvatars(uris: string[]) {
    setUploading(true);

    try {
      for (const uri of uris) {
        const payload = await uploadSingleSocialPhoto(uri);
        applyPhotoResponse(payload);
      }

      Alert.alert(
        'Listo',
        uris.length === 1
          ? 'La foto se agrego a tu perfil social.'
          : `${uris.length} fotos se agregaron a tu perfil social.`
      );
    } catch (error) {
      Alert.alert('No se pudo subir la foto', getApiError(error));
    } finally {
      setUploading(false);
    }
  }

  async function handleSetPrimaryPhoto(photoUrl: string) {
    try {
      setUploading(true);
      const response = await apiClient.post<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>(
        '/users/social-profile/photo/primary',
        { photo_url: photoUrl }
      );

      applyPhotoResponse(unwrapApiData(response.data));
    } catch (error) {
      Alert.alert('No se pudo cambiar la principal', getApiError(error));
    } finally {
      setUploading(false);
    }
  }

  async function handleDeletePhoto(photoUrl: string) {
    Alert.alert('Eliminar foto', 'Esta foto se quitará de tu perfil social.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          try {
            setUploading(true);
            const response = await apiClient.delete<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>(
              '/users/social-profile/photo',
              { data: { photo_url: photoUrl } }
            );

            applyPhotoResponse(unwrapApiData(response.data));
          } catch (error) {
            Alert.alert('No se pudo eliminar', getApiError(error));
          } finally {
            setUploading(false);
          }
        },
      },
    ]);
  }

  async function requestSocialActivation(mesaValue: string) {
    if (requiresSocialConsent) {
      setPendingSocialMesaValue(mesaValue);
      setSocialConsentChecked(false);
      setConsentModalVisible(true);
      return;
    }

    await persistSocialStatus(true, mesaValue);
  }

  async function handleAcceptSocialConsent() {
    if (!socialConsentChecked) {
      Alert.alert('Confirmación requerida', 'Marca la casilla para confirmar que aceptas compartir tus datos sociales.');
      return;
    }

    if (!pendingSocialMesaValue) {
      setConsentModalVisible(false);
      return;
    }

    const mesaValue = pendingSocialMesaValue;
    setConsentModalVisible(false);
    setPendingSocialMesaValue(null);
    await persistSocialStatus(true, mesaValue, true);
  }

  function handleCancelSocialConsent() {
    setConsentModalVisible(false);
    setSocialConsentChecked(false);
    setPendingSocialMesaValue(null);
  }

  async function openPrivacyNotice() {
    try {
      await Linking.openURL(SOCIAL_PRIVACY_NOTICE_URL);
    } catch {
      Alert.alert('Aviso de privacidad', SOCIAL_PRIVACY_NOTICE_URL);
    }
  }

  function showVisitBranchMessage() {
    Alert.alert(
      'Visítanos en una sucursal',
      'Visítanos en alguna sucursal para conocer a los comensales.',
      [{ text: 'Entendido' }]
    );
  }

  function openSocialScanner() {
    const branchForScanner = selectedBranch ?? deferredBranch ?? tableSession?.branch ?? null;

    if (!branchForScanner?.id) {
      showVisitBranchMessage();
      return;
    }

    router.push({
      pathname: '/table-scanner',
      params: {
        returnTo: '/profile/social',
        activateSocial: '1',
        mode: 'eat_in',
        branchId: String(branchForScanner.id),
      },
    });
  }

  async function updateSocialStatus(nextValue: boolean) {
    if (nextValue && !selectedBranch?.id && !deferredBranch?.id) {
      openSocialScanner();
      return;
    }

    if (nextValue && !hasCompleteProfile) {
      Alert.alert(
        'Completa tu perfil',
        'Necesitas foto, edad, genero, sexualidad y descripcion para aparecer a otros comensales.',
        [{ text: 'Editar perfil', onPress: () => setModalVisible(true) }, { text: 'Cerrar', style: 'cancel' }]
      );
      return;
    }

    if (nextValue) {
      if (tableSession?.mesaValue) {
        await requestSocialActivation(tableSession.mesaValue);
        return;
      }

      openSocialScanner();
      return;
    }

    await persistSocialStatus(false, null);
  }

  async function openMesaPrompt() {
    openSocialScanner();
  }

  async function persistSocialStatus(nextValue: boolean, mesaValue: string | null, acceptsSocialPrivacy = false) {
    try {
      setStatusUpdating(true);
      const socialRestaurantId = tableSession?.restauranteId ?? selectedBranch?.id ?? null;
      const response = await apiClient.post<ApiEnvelope<SocialStatusResponse> | SocialStatusResponse>('/users/social-status', {
        is_social_active: nextValue,
        current_restaurante_id: nextValue ? socialRestaurantId : null,
        mesa: nextValue ? mesaValue : null,
        accepts_social_privacy: nextValue ? acceptsSocialPrivacy : false,
      }, {
        _suppressConsoleError: nextValue,
      } as never);
      const result = unwrapApiData(response.data);

      if (!nextValue) {
        setDiners([]);
        setCurrentIndex(0);
        setDinerDetails({});
      }

      setMesaInput(nextValue ? mesaValue ?? '' : '');
      setMesaModalVisible(false);
      setPendingActivationAfterBranch(false);
      setModoSocial(nextValue);
      setRequiresSocialConsent(Boolean(result.requires_social_consent));
      updateProfile({
        is_social_active: nextValue,
        modo_social: nextValue,
        current_restaurante_id: nextValue ? socialRestaurantId : null,
        mesa: nextValue ? normalizeMesaValue(mesaValue) : null,
        social_consent_accepted_at: result.social_consent_accepted_at ?? user?.social_consent_accepted_at ?? null,
        social_consent_version: result.social_consent_version ?? user?.social_consent_version ?? null,
        requires_social_consent: Boolean(result.requires_social_consent),
      });
    } catch (error) {
      const message = getApiError(error);
      const normalizedMessage = message.toLowerCase();

      if (normalizedMessage.includes('debes completar tu perfil social')) {
        setHasCompleteProfile(false);
        setModoSocial(false);
        setMesaModalVisible(false);
        setPendingActivationAfterBranch(false);
        setModalVisible(true);
        return;
      }

      if (nextValue && normalizedMessage.includes('aviso de privacidad')) {
        setRequiresSocialConsent(true);
        if (mesaValue) {
          setPendingSocialMesaValue(mesaValue);
          setSocialConsentChecked(false);
          setConsentModalVisible(true);
        }
        return;
      }

      Alert.alert('Modo social', message);
    } finally {
      setStatusUpdating(false);
    }
  }

  async function handleConfirmMesa() {
    const cleanMesa = mesaInput.trim();

    if (!cleanMesa) {
      Alert.alert('Mesa requerida', 'Ingresa tu número de mesa para activar el modo social.');
      return;
    }

    await persistSocialStatus(true, cleanMesa);
  }

  async function handleSaveProfile() {
    const edad = Number(form.edad);

    if (!Number.isFinite(edad) || edad <= 0) {
      Alert.alert('Edad inválida', 'Ingresa una edad válida.');
      return;
    }

    if (!form.nombre.trim()) {
      Alert.alert('Nombre requerido', 'Ingresa el nombre que quieres mostrar en tu perfil social.');
      return;
    }

    if (!form.genero || !form.sexualidad || !form.biografia.trim()) {
      Alert.alert('Perfil incompleto', 'Género, sexualidad y descripción son obligatorios.');
      return;
    }

    try {
      setSaving(true);

      const payload = {
        nombre: form.nombre.trim(),
        edad,
        genero: form.genero,
        sexualidad: form.sexualidad,
        descripcion: form.biografia.trim(),
        intereses: selectedInterests.length > 0 ? selectedInterests.join(', ') : null,
        que_busca: form.queBusca || null,
        redes_sociales: stringifySocialNetworks(form.instagram, form.tiktok),
      };

      const response = await apiClient.put<ApiEnvelope<SocialProfileResponse> | SocialProfileResponse>(
        '/users/social-profile',
        payload
      );
      const data = unwrapApiData(response.data);
      const redes = parseSocialNetworks(data.redes_sociales);
      const photos = normalizePhotoList(data.social_photos, data.foto_url ?? user?.foto_url ?? null);
      const photoUrl = photos[0] ?? resolvePhotoUrl(data.foto_url) ?? user?.foto_url ?? null;
      const ready =
        data.has_social_profile ??
        isProfileReady({
          foto_url: photoUrl,
          edad: data.edad,
          sexualidad: data.sexualidad,
          genero: data.genero,
          descripcion: data.descripcion,
        });

      setHasCompleteProfile(ready);
      setSocialPhotos(photos);
      setModalVisible(false);

      updateProfile({
        nombre: data.nombre,
        foto_url: photoUrl,
        social_photos: photos,
        edad: data.edad,
        genero: data.genero,
        sexualidad: data.sexualidad,
        gustos: data.intereses,
        biografia: data.descripcion,
        que_busca: data.que_busca,
        redes_sociales: data.redes_sociales,
        instagram: redes.instagram || null,
        tiktok: redes.tiktok || null,
      });

      Alert.alert('Perfil guardado', 'Tu perfil social quedó actualizado.');
    } catch (error) {
      Alert.alert('No se pudo guardar', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  function closeLikeGiftPrompt() {
    setLikeGiftPromptVisible(false);
    setLikeGiftPromptDiner(null);
    setLikeGiftPromptMatched(false);
  }

  async function handleGiftAfterLike() {
    const recipient = likeGiftPromptDiner;
    closeLikeGiftPrompt();
    if (recipient) {
      await new Promise((resolve) => setTimeout(resolve, Platform.OS === 'ios' ? 180 : 80));
      await openGiftSelector(recipient);
    }
  }

  async function openGiftSelector(targetDiner?: unknown) {
    const explicitTarget = isSocialDiner(targetDiner) ? targetDiner : null;
    const giftRecipient = explicitTarget ?? detailDiner;
    if (!giftRecipient) return;

    if (explicitTarget) {
      setFocusedDiner(explicitTarget);
    }

    if (detailsVisible) {
      setDetailsVisible(false);
      await new Promise((resolve) => setTimeout(resolve, Platform.OS === 'ios' ? 320 : 160));
    }

    try {
      setGiftsVisible(true);
      const restaurantId = selectedBranch?.id ?? null;

      if (giftProducts.length > 0 && giftProductsRestaurantId === restaurantId) {
        return;
      }

      setGiftProducts([]);
      setGiftProductsRestaurantId(null);
      setSelectedGiftId(null);
      setGiftProductsLoading(true);
      const response = await apiClient.get<{ success?: boolean; data?: GiftProduct[] } | GiftProduct[]>('/gift-products', {
        params: restaurantId ? { restaurant_id: restaurantId } : undefined,
      });
      const rawItems: unknown[] = Array.isArray(response.data)
        ? response.data
        : Array.isArray(response.data?.data)
          ? response.data.data
          : [];

      const normalized = rawItems
        .filter(isGiftProductItem)
        .map(normalizeGiftProduct)
        .filter((item) => item.id > 0 && item.es_regalo !== false);
      setGiftProducts(normalized);
      setGiftProductsRestaurantId(restaurantId);
      setSelectedGiftId((current) => current ?? normalized[0]?.id ?? null);
    } catch (error) {
      Alert.alert('Regalos', getApiError(error));
      closeGiftSelector();
    } finally {
      setGiftProductsLoading(false);
    }
  }

  function closeGiftSelector() {
    if (giftSending) return;
    resetGiftComposer();
  }

  function resetGiftComposer() {
    setGiftsVisible(false);
    setFocusedDiner(null);
    setGiftCheckoutMode('account');
    setGiftCardComplete(false);
    setGiftRewardsQuote(null);
    setUseGiftRewardsPoints(false);
    giftRequestKeyRef.current = null;
  }

  function getGiftRequestKey() {
    giftRequestKeyRef.current ??= `gift_${Date.now()}_${Math.random().toString(36).slice(2, 14)}`;
    return giftRequestKeyRef.current;
  }

  function selectGiftCheckoutMode(mode: GiftCheckoutMode) {
    if (giftSending) return;
    giftRequestKeyRef.current = null;
    setGiftCardComplete(false);
    setUseGiftRewardsPoints(false);
    setGiftCheckoutMode(mode);
  }

  function showGiftSentAlert(gift: SocialGiftOrder, paymentMode: GiftCheckoutMode) {
    const giftName = gift.gift_nombre ?? selectedGift?.nombre ?? 'tu regalo';
    const recipientName = gift.recipient_nombre ?? detailDiner?.nombre ?? 'el comensal';
    const mesaLabel = gift.mesa_label ?? (detailDiner?.mesa ? `Mesa ${detailDiner.mesa}` : 'la mesa del comensal');
    const folio = gift.folio;
    const amount = Number(gift.gift_precio ?? selectedGift?.precio ?? 0).toFixed(2);
    const message =
      paymentMode === 'stripe'
        ? `Cobramos $${amount} con Stripe y avisamos al equipo para entregar "${giftName}" a ${recipientName} en ${mesaLabel}${folio ? `.\nFolio: ${folio}` : '.'}`
        : paymentMode === 'wallet'
          ? `Usamos tu Saldo Amare y avisamos al equipo para entregar "${giftName}" a ${recipientName} en ${mesaLabel}${folio ? `.\nFolio: ${folio}` : '.'}`
        : `Agregamos $${amount} a la cuenta de tu mesa y avisamos al equipo para entregar "${giftName}" a ${recipientName} en ${mesaLabel}${folio ? `.\nFolio: ${folio}` : '.'}`;

    resetGiftComposer();
    Alert.alert('Regalo enviado', message);
  }

  async function sendGiftToAccount() {
    const gift = selectedGift;
    const diner = detailDiner;
    if (!gift || !diner || !selectedBranch?.id) {
      throw new Error('Faltan datos para enviar el regalo.');
    }

    const payload = await createSocialGiftPayment({
      restaurant_id: selectedBranch.id,
      recipient_user_id: diner.user_id,
      gift_product_id: gift.id,
      gift_type: getGiftProductType(gift),
      request_key: getGiftRequestKey(),
      payment_mode: 'account',
    });

    if (!payload?.gift?.id || !payload.charged_to_account) {
      throw new Error('No se pudo cargar el regalo a tu cuenta.');
    }

    showGiftSentAlert(payload.gift, 'account');
  }

  async function sendGiftWithStripe() {
    const gift = selectedGift;
    const diner = detailDiner;
    if (!gift || !diner || !selectedBranch?.id) {
      throw new Error('Faltan datos para enviar el regalo.');
    }
    if (!stripeAvailable) {
      throw new Error('Stripe no esta configurado en esta app. Agrega EXPO_PUBLIC_STRIPE_KEY para usar pago inmediato.');
    }
    if (!giftCardComplete) {
      throw new Error('Completa los datos de tu tarjeta antes de pagar el regalo.');
    }

    const requestKey = getGiftRequestKey();
    console.log('[SocialGift][Stripe] Iniciando pago', {
      restaurantId: selectedBranch.id,
      recipientUserId: diner.user_id,
      giftProductId: gift.id,
      requestKey,
      stripeAvailable,
      giftCardComplete,
      publishableKeyPreview: summarizeStripeKey(STRIPE_PUBLISHABLE_KEY),
    });

    const payload = await createSocialGiftPayment({
      restaurant_id: selectedBranch.id,
      recipient_user_id: diner.user_id,
      gift_product_id: gift.id,
      gift_type: getGiftProductType(gift),
      request_key: requestKey,
      payment_mode: 'stripe',
    });

    console.log('[SocialGift][Stripe] Backend preparo pago', {
      giftId: payload?.gift?.id ?? null,
      status: payload?.gift?.status ?? null,
      paymentIntentId: payload?.payment_intent_id ?? null,
      clientSecretPreview: summarizeStripeSecret(payload?.client_secret),
      requestKey,
    });

    if (!payload?.gift?.id || !payload.client_secret || !payload.payment_intent_id) {
      console.error('[SocialGift][Stripe] Respuesta incompleta al preparar pago', payload);
      throw new Error('No se pudo preparar el cobro del regalo con Stripe.');
    }

    console.log('[SocialGift][Stripe] Confirmando tarjeta en Stripe', {
      giftId: payload.gift.id,
      paymentIntentId: payload.payment_intent_id,
      requestKey,
    });
    const { error } = await stripeConfirm(payload.client_secret, {
      paymentMethodType: 'Card',
    });
    if (error) {
      console.error('[SocialGift][Stripe] Stripe confirmPayment fallo', {
        message: error.message,
        code: 'code' in error ? (error as { code?: string }).code ?? null : null,
        declineCode: 'declineCode' in error ? (error as { declineCode?: string }).declineCode ?? null : null,
        localizedMessage:
          'localizedMessage' in error
            ? (error as { localizedMessage?: string }).localizedMessage ?? null
            : null,
        stripeError: error,
        paymentIntentId: payload.payment_intent_id,
        requestKey,
      });
      throw new Error(error.message || 'Stripe no pudo confirmar el pago.');
    }

    console.log('[SocialGift][Stripe] Stripe confirmo la tarjeta, verificando backend', {
      giftId: payload.gift.id,
      paymentIntentId: payload.payment_intent_id,
      requestKey,
    });
    const paidGift = await confirmSocialGiftPayment(payload.gift.id);
    console.log('[SocialGift][Stripe] Backend confirmo regalo pagado', {
      giftId: paidGift?.id ?? payload.gift.id,
      status: paidGift?.status ?? null,
      pedidoId: paidGift?.pedido_id ?? null,
      pedidoItemId: paidGift?.pedido_item_id ?? null,
      requestKey,
    });
    showGiftSentAlert(paidGift, 'stripe');
  }

  async function sendGiftWithWallet() {
    const gift = selectedGift;
    const diner = detailDiner;
    if (!gift || !diner || !selectedBranch?.id) {
      throw new Error('Faltan datos para enviar el regalo.');
    }
    if (!giftRewardsQuote?.can_pay) {
      throw new Error('Tu Saldo Amare no alcanza para enviar este regalo.');
    }

    const payload = await createSocialGiftPayment({
      restaurant_id: selectedBranch.id,
      recipient_user_id: diner.user_id,
      gift_product_id: gift.id,
      gift_type: getGiftProductType(gift),
      request_key: getGiftRequestKey(),
      payment_mode: 'wallet',
      use_points: useGiftRewardsPoints,
    });

    if (!payload?.gift?.id || !payload.paid_with_wallet) {
      throw new Error('No se pudo pagar el regalo con Saldo Amare.');
    }

    const wallet = await getRewardsWallet().catch(() => null);
    if (wallet) setGiftRewardsWallet(wallet);
    showGiftSentAlert(payload.gift, 'wallet');
  }

  async function submitGiftAfterTerms() {
    if (!detailDiner || !selectedGift) {
      Alert.alert('Regalos', 'Selecciona un regalo para continuar.');
      return;
    }

    if (!selectedBranch?.id) {
      Alert.alert('Visítanos en una sucursal', 'Visítanos en alguna sucursal para conocer a los comensales.');
      return;
    }

    try {
      setGiftSending(true);
      if (giftCheckoutMode === 'stripe') {
        await sendGiftWithStripe();
      } else if (giftCheckoutMode === 'wallet') {
        await sendGiftWithWallet();
      } else {
        await sendGiftToAccount();
      }
    } catch (error) {
      if (giftCheckoutMode === 'stripe' || giftCheckoutMode === 'wallet') {
        console.error('[SocialGift] Flujo de pago fallo', {
          requestKey: giftRequestKeyRef.current,
          message: getApiError(error),
          error,
        });
        giftRequestKeyRef.current = null;
      }
      Alert.alert(giftCheckoutMode === 'stripe' ? 'No se pudo cobrar el regalo' : 'No se pudo enviar', getApiError(error));
    } finally {
      setGiftSending(false);
    }
  }

  async function handleSendGift() {
    if (!detailDiner || !selectedGift) {
      Alert.alert('Regalos', 'Selecciona un regalo para continuar.');
      return;
    }

    if (!selectedBranch?.id) {
      Alert.alert('Regalos', 'Selecciona una sucursal antes de enviar un regalo.');
      return;
    }

    Alert.alert('Condiciones del regalo', GIFT_TERMS_MESSAGE, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Aceptar y enviar', onPress: () => void submitGiftAfterTerms() },
    ]);
  }

  function showSexualityInfo(value?: string | null) {
    if (!value) return;
    const description = getSexualityDescription(value);
    Alert.alert(value, description || 'Esta persona eligió compartir esta información en su perfil.');
  }

  function renderSexualityChip(value?: string | null) {
    if (!value) return null;
    const hasDescription = Boolean(getSexualityDescription(value));

    return (
      <TouchableOpacity
        style={[styles.metaChip, styles.metaChipInteractive]}
        activeOpacity={0.75}
        onPress={() => showSexualityInfo(value)}
        accessibilityRole="button"
        accessibilityLabel={`Información sobre ${value}`}
      >
        <Text style={styles.metaChipText}>{value}</Text>
        {hasDescription ? <Ionicons name="information-circle-outline" size={15} color={Colors.primary} /> : null}
      </TouchableOpacity>
    );
  }

  function renderSwipeCard(
    diner: SocialDiner,
    options?: {
      onPress?: () => void;
      hintText?: string;
    }
  ) {
    const cardBody = (
      <>
        <TouchableOpacity
          activeOpacity={0.92}
          onPress={() => {
            options?.onPress?.();
          }}
          style={styles.dinerImageWrap}
        >
          {diner.foto_url ? (
            <Image source={{ uri: diner.foto_url }} style={styles.dinerImage} contentFit="cover" cachePolicy="disk" />
          ) : (
            <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.dinerImageFallback}>
              <Text style={styles.dinerImageFallbackLetter}>{diner.nombre[0]?.toUpperCase() ?? '?'}</Text>
            </LinearGradient>
          )}

          {isLikedOrMatched(diner) ? (
            <View style={styles.likedBadge}>
              <Ionicons name="heart" size={16} color={Colors.white} />
              <Text style={styles.likedBadgeText}>{diner.relationship_status === 'matched' ? 'Match' : 'Like enviado'}</Text>
            </View>
          ) : null}

          <LinearGradient colors={['transparent', 'rgba(26,26,46,0.72)']} style={styles.dinerImageOverlay}>
            <Text style={styles.dinerImageName}>{diner.nombre}</Text>
            <Text style={styles.dinerImageHint}>
              {options?.hintText ?? 'Toca la foto para ver su perfil completo'}
            </Text>
          </LinearGradient>
        </TouchableOpacity>

        <View style={styles.dinerPreview}>
          {diner.que_busca ? <Text style={styles.previewEyebrow}>{diner.que_busca}</Text> : null}

          <View style={styles.previewMetaWrap}>
            {diner.mesa ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>Mesa {diner.mesa}</Text>
              </View>
            ) : null}
            {diner.edad ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>{diner.edad} años</Text>
              </View>
            ) : null}
            {diner.genero ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>{diner.genero}</Text>
              </View>
            ) : null}
            {renderSexualityChip(diner.sexualidad)}
          </View>
        </View>
      </>
    );

    return <View style={styles.dinerCard}>{cardBody}</View>;
  }

  function renderDinerDetailsContent(diner: SocialDiner) {
    const networks = parseSocialNetworks(diner.redes_sociales);
    const primaryHandle = formatHandleLabel(networks.instagram || networks.tiktok);

    return (
      <View style={styles.dinerContent}>
        <Text style={styles.dinerName}>{diner.nombre}</Text>
        {diner.que_busca ? <Text style={styles.dinerSubtitle}>{diner.que_busca}</Text> : null}

        <View style={styles.metaWrap}>
          {diner.mesa ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>Mesa {diner.mesa}</Text>
            </View>
          ) : null}
          {diner.edad ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>{diner.edad} años</Text>
            </View>
          ) : null}
          {diner.genero ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>{diner.genero}</Text>
            </View>
          ) : null}
          {renderSexualityChip(diner.sexualidad)}
        </View>

        {diner.descripcion ? <Text style={styles.descriptionText}>{diner.descripcion}</Text> : null}

        {primaryHandle ? (
          <View style={styles.infoSection}>
            <Text style={styles.infoLabel}>Redes sociales</Text>
            <Text style={styles.infoValue}>{primaryHandle}</Text>
          </View>
        ) : null}

        {diner.intereses.length > 0 ? (
          <View style={styles.infoSection}>
            <Text style={styles.infoLabel}>Intereses</Text>
            <View style={styles.interestWrap}>
              {diner.intereses.map((interest) => (
                <View key={interest} style={styles.interestChip}>
                  <Text style={styles.interestChipText}>{interest}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {canUseSocialActions && diner.relationship_status !== 'matched' ? (
          <TouchableOpacity
            activeOpacity={0.88}
            onPress={() => (diner.relationship_status === 'liked' ? handleUnlikeDiner(diner.user_id) : handleLikeDiner(diner.user_id))}
            style={[styles.likeDetailButton, likingUserId === diner.user_id && styles.saveButtonDisabled]}
            disabled={likingUserId === diner.user_id}
          >
            {likingUserId === diner.user_id ? (
              <ActivityIndicator size="small" color={Colors.white} />
            ) : (
              <Ionicons name={diner.relationship_status === 'liked' ? 'heart' : 'heart-outline'} size={20} color={Colors.white} />
            )}
            <Text style={styles.giftButtonText}>
              {diner.relationship_status === 'liked' ? 'Like enviado' : 'Me gusta'}
            </Text>
          </TouchableOpacity>
        ) : null}

        {canUseSocialActions ? (
          <TouchableOpacity activeOpacity={0.88} onPress={() => openGiftSelector()} style={styles.giftButton}>
            <Ionicons name="gift-outline" size={20} color={Colors.white} />
            <Text style={styles.giftButtonText}>Regalo</Text>
          </TouchableOpacity>
        ) : null}

        {canUseSocialActions ? (
          <TouchableOpacity activeOpacity={0.88} onPress={() => openCoverAccountModal(diner)} style={styles.coverAccountButton}>
            <Ionicons name="receipt-outline" size={20} color={Colors.primary} />
            <Text style={styles.coverAccountButtonText}>Cubrir su cuenta</Text>
          </TouchableOpacity>
        ) : null}
      </View>
    );
  }

  function renderCurrentDiner() {
    if (dinersLoading) {
      return (
        <View style={styles.centerStateCard}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.centerStateTitle}>Buscando comensales</Text>
          <Text style={styles.centerStateText}>Estamos cargando a las personas activas en esta sucursal.</Text>
        </View>
      );
    }

    if (!selectedBranch?.id) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="location-outline" size={54} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Visítanos en una sucursal</Text>
          <Text style={styles.centerStateText}>
            Visítanos en alguna sucursal para conocer a los comensales.
          </Text>
        </View>
      );
    }

    if (!currentDiner) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="people-outline" size={58} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Aún no hay comensales visibles</Text>
          <Text style={styles.centerStateText}>
            Cuando más personas activen su modo social en {selectedBranch.nombre}, aparecerán aquí.
          </Text>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => setModalVisible(true)}>
            <Ionicons name="create-outline" size={18} color={Colors.primary} />
            <Text style={styles.secondaryButtonText}>Editar mi perfil social</Text>
          </TouchableOpacity>
        </View>
      );
    }

    const currentDinerHasRelationship = currentDiner.relationship_status === 'matched';
    const currentDinerLikeLabel =
      currentDiner.relationship_status === 'matched'
        ? 'Match'
        : currentDiner.relationship_status === 'liked'
          ? isCompactDiscovery
            ? 'Enviado'
            : 'Like enviado'
          : 'Me gusta';
    const giftButtonDisabled = !currentDiner || giftSending;

    return (
      <View style={styles.carouselArea}>
        <View style={styles.tinderCardContainer}>
          {currentDiner && (
            <GestureDetector gesture={panGesture}>
              <Animated.View style={[styles.tinderCard, animatedCardStyle]}>
                {renderSwipeCard(currentDiner, {
                  onPress: () => handleDinerCardPress(),
                })}
              </Animated.View>
            </GestureDetector>
          )}

          <View style={styles.discoveryActions}>
            <TouchableOpacity
              style={[styles.discoveryArrowButton, diners.length <= 1 && styles.discoveryArrowButtonDisabled]}
              activeOpacity={0.86}
              onPress={() => advanceToDiner('prev')}
              disabled={diners.length <= 1}
              accessibilityRole="button"
              accessibilityLabel="Perfil anterior"
            >
              <Ionicons name="chevron-back" size={26} color={diners.length <= 1 ? Colors.textMuted : Colors.text} />
            </TouchableOpacity>

            <View style={styles.discoveryActionCenter}>
              <TouchableOpacity
                style={[
                  styles.likeButton,
                  (likingUserId === currentDiner?.user_id || currentDinerHasRelationship) && styles.saveButtonDisabled,
                ]}
                activeOpacity={0.86}
                onPress={() =>
                  currentDiner &&
                  (currentDiner.relationship_status === 'liked'
                    ? handleUnlikeDiner(currentDiner.user_id)
                    : handleLikeDiner(currentDiner.user_id))
                }
                disabled={!currentDiner || likingUserId === currentDiner.user_id || currentDinerHasRelationship}
              >
                {likingUserId === currentDiner?.user_id ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Ionicons name="heart" size={20} color={Colors.white} />
                )}
                <Text style={styles.discoveryButtonText} numberOfLines={1}>
                  {currentDinerLikeLabel}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.discoveryGiftButton, giftButtonDisabled && styles.saveButtonDisabled]}
                activeOpacity={0.86}
                onPress={() => openGiftSelector()}
                disabled={giftButtonDisabled}
              >
                <Text style={styles.discoveryButtonText} numberOfLines={1}>
                  Enviar
                </Text>
                {giftProductsLoading ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Ionicons name="gift-outline" size={20} color={Colors.white} />
                )}

              </TouchableOpacity>
            </View>

            <TouchableOpacity
              style={[styles.discoveryArrowButton, diners.length <= 1 && styles.discoveryArrowButtonDisabled]}
              activeOpacity={0.86}
              onPress={handleNextDiner}
              disabled={diners.length <= 1}
              accessibilityRole="button"
              accessibilityLabel="Siguiente perfil"
            >
              <Ionicons name="chevron-forward" size={26} color={diners.length <= 1 ? Colors.textMuted : Colors.text} />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  }

  function renderMatchesView() {
    if (matchesLoading) {
      return (
        <View style={styles.centerStateCard}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.centerStateTitle}>Cargando tus matches</Text>
          <Text style={styles.centerStateText}>Estamos buscando las personas que también te dieron like.</Text>
        </View>
      );
    }

    if (matches.length === 0) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="heart-outline" size={70} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Aún no tienes matches</Text>
          <Text style={styles.centerStateText}>
            Da like a personas que te interesen. Si también te dan like, aparecerán aquí.
          </Text>
          {canDiscover ? (
            <TouchableOpacity style={styles.primaryButton} onPress={() => setSocialView('discover')} activeOpacity={0.85}>
              <Ionicons name="people-outline" size={18} color={Colors.white} />
              <Text style={styles.primaryButtonText}>Descubrir comensales</Text>
            </TouchableOpacity>
          ) : null}
        </View>
      );
    }

    return (
      <ScrollView contentContainerStyle={styles.matchesList} showsVerticalScrollIndicator={false}>
        {matches.map((match) => (
          <TouchableOpacity
            key={match.user_id}
            activeOpacity={0.88}
            style={styles.matchCard}
            onPress={() => handleDinerCardPress(match)}
          >
            {match.foto_url ? (
              <Image source={{ uri: match.foto_url }} style={styles.matchImage} contentFit="cover" cachePolicy="disk" />
            ) : (
              <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.matchFallback}>
                <Text style={styles.matchFallbackLetter}>{match.nombre[0]?.toUpperCase() ?? '?'}</Text>
              </LinearGradient>
            )}

            <View style={styles.matchInfo}>
              <View style={styles.matchTitleRow}>
                <Text numberOfLines={1} style={styles.matchName}>{match.nombre}</Text>
                <Ionicons name="heart" size={18} color="#E11D48" />
              </View>
              <Text numberOfLines={1} style={styles.matchMeta}>
                {match.mesa ? `Mesa ${match.mesa}` : 'Match social'}
              </Text>
              {match.que_busca ? <Text numberOfLines={1} style={styles.matchIntent}>{match.que_busca}</Text> : null}
              {formatMatchDate(match.matched_at) ? (
                <Text numberOfLines={1} style={styles.matchDate}>Match desde {formatMatchDate(match.matched_at)}</Text>
              ) : null}
            </View>

            <Ionicons name="chevron-forward" size={20} color={Colors.textMuted} />
          </TouchableOpacity>
        ))}
      </ScrollView>
    );
  }

  function renderLikesView() {
    const likes = likesView === 'received' ? receivedLikes : sentLikes;
    const emptyTitle = likesView === 'received' ? 'Aún no te dan likes' : 'Aún no has dado likes';
    const emptyText =
      likesView === 'received'
        ? 'Cuando alguien te dé me gusta, aparecerá aquí para que puedas ver su perfil.'
        : 'Los perfiles a los que les des like aparecerán aquí mientras no hagan match.';

    if (likesLoading && likes.length === 0) {
      return (
        <View style={styles.centerStateCard}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.centerStateTitle}>Cargando likes</Text>
          <Text style={styles.centerStateText}>Estamos reuniendo tus conexiones pendientes.</Text>
        </View>
      );
    }

    return (
      <View style={styles.likesViewWrap}>
        <View style={styles.likesSegmentedControl}>
          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.likesSegmentButton, likesView === 'received' && styles.likesSegmentButtonActive]}
            onPress={() => setLikesView('received')}
          >
            <Text style={[styles.likesSegmentText, likesView === 'received' && styles.likesSegmentTextActive]}>
              Me dieron {receivedLikes.length > 0 ? `(${receivedLikes.length})` : ''}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.likesSegmentButton, likesView === 'sent' && styles.likesSegmentButtonActive]}
            onPress={() => setLikesView('sent')}
          >
            <Text style={[styles.likesSegmentText, likesView === 'sent' && styles.likesSegmentTextActive]}>
              He dado {sentLikes.length > 0 ? `(${sentLikes.length})` : ''}
            </Text>
          </TouchableOpacity>
        </View>

        {likes.length === 0 ? (
          <View style={styles.centerStateCard}>
            <Ionicons name={likesView === 'received' ? 'heart-outline' : 'send-outline'} size={64} color={Colors.textMuted} />
            <Text style={styles.centerStateTitle}>{emptyTitle}</Text>
            <Text style={styles.centerStateText}>{emptyText}</Text>
          </View>
        ) : (
          <ScrollView contentContainerStyle={styles.matchesList} showsVerticalScrollIndicator={false}>
            {likes.map((like) => {
              const likedDate = formatMatchDate(like.liked_at);
              const metaLabel =
                likesView === 'received'
                  ? like.mesa
                    ? `Mesa ${like.mesa}`
                    : 'Te dio me gusta'
                  : like.mesa
                    ? `Mesa ${like.mesa}`
                    : 'Le diste me gusta';

              return (
                <TouchableOpacity
                  key={`${likesView}-${like.user_id}`}
                  activeOpacity={0.88}
                  style={styles.matchCard}
                  onPress={() => handleLikeListPress(like)}
                >
                  {like.foto_url ? (
                    <Image source={{ uri: like.foto_url }} style={styles.matchImage} contentFit="cover" cachePolicy="disk" />
                  ) : (
                    <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.matchFallback}>
                      <Text style={styles.matchFallbackLetter}>{like.nombre[0]?.toUpperCase() ?? '?'}</Text>
                    </LinearGradient>
                  )}

                  <View style={styles.matchInfo}>
                    <View style={styles.matchTitleRow}>
                      <Text numberOfLines={1} style={styles.matchName}>{like.nombre}</Text>
                      <Ionicons name={likesView === 'received' ? 'heart' : 'paper-plane-outline'} size={18} color="#E11D48" />
                    </View>
                    <Text numberOfLines={1} style={styles.matchMeta}>{metaLabel}</Text>
                    {like.que_busca ? <Text numberOfLines={1} style={styles.matchIntent}>{like.que_busca}</Text> : null}
                    {likedDate ? (
                      <Text numberOfLines={1} style={styles.matchDate}>
                        {likesView === 'received' ? `Like recibido el ${likedDate}` : `Like enviado el ${likedDate}`}
                      </Text>
                    ) : null}
                  </View>

                  <Ionicons name="chevron-forward" size={20} color={Colors.textMuted} />
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        )}
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <Stack.Screen options={{ headerShown: false }} />

      <LinearGradient colors={[Colors.background, Colors.background]} style={styles.heroBackground}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.iconButton} onPress={() => router.back()} activeOpacity={0.8}>
            <Ionicons name="arrow-back" size={22} color={Colors.text} />
          </TouchableOpacity>

          <View style={styles.headerTextWrap}>
            <Text style={styles.headerTitle}>Comensales</Text>
            <Text style={styles.headerSubtitle}>
              {selectedBranch?.nombre ? `En ${selectedBranch.nombre}` : 'Activa tu modo social para descubrir gente'}
            </Text>
          </View>

          <TouchableOpacity style={styles.iconButton} onPress={() => setModalVisible(true)} activeOpacity={0.8}>
            <Ionicons name="settings-outline" size={22} color={Colors.text} />
          </TouchableOpacity>
        </View>

        <View style={styles.statusCard}>
          <View style={styles.statusTextBlock}>
            <View style={[styles.statusDot, { backgroundColor: modoSocial ? '#21A453' : '#D0D5DD' }]} />
            <View style={styles.statusBody}>
              <Text style={styles.statusTitle}>Modo social {modoSocial ? 'encendido' : 'apagado'}</Text>
            </View>
          </View>

          <Switch
            style={styles.statusSwitch}
            value={modoSocial}
            onValueChange={updateSocialStatus}
            disabled={statusUpdating}
            trackColor={{ false: '#D6DAE5', true: '#89A0E8' }}
            thumbColor={modoSocial ? Colors.primary : Colors.white}
            ios_backgroundColor="#D6DAE5"
          />
        </View>

        <TableContextBanner
          session={tableSession}
          branchName={selectedBranch?.nombre}
          variant="compact"
          title="En mesa"
          style={styles.currentTablePill}
        />

        {canDiscover ? (
          <View style={styles.summaryRow}>
            <TouchableOpacity
              style={[styles.summaryPill, socialView === 'discover' && styles.summaryPillActive]}
              activeOpacity={0.82}
              onPress={() => setSocialView('discover')}
            >
              <Ionicons
                name="people-outline"
                size={18}
                color={socialView === 'discover' ? Colors.white : Colors.primary}
              />
              <Text
                numberOfLines={1}
                style={[styles.summaryPillText, socialView === 'discover' && styles.summaryPillTextActive]}
              >
                Descubrir
              </Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.summaryPill} activeOpacity={0.8} onPress={() => setFiltersVisible(true)}>
              <Ionicons name="options-outline" size={18} color={Colors.primary} />
              <Text numberOfLines={1} style={styles.summaryPillText}>
                {activeFilterCount > 0 ? `Filtros (${activeFilterCount})` : 'Filtros'}
              </Text>
            </TouchableOpacity>
          </View>
        ) : null}

        <View style={styles.socialViewSwitch}>
          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.socialViewButton, socialView === 'matches' && styles.socialViewButtonActive]}
            onPress={() => setSocialView('matches')}
          >
            <Ionicons
              name="heart-outline"
              size={17}
              color={socialView === 'matches' ? Colors.white : Colors.primary}
            />
            <Text numberOfLines={1} style={[styles.socialViewText, socialView === 'matches' && styles.socialViewTextActive]}>
              Matches{matches.length > 0 ? ` (${matches.length})` : ''}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.socialViewButton, socialView === 'likes' && styles.socialViewButtonActive]}
            onPress={() => handleOpenLikesView('received')}
          >
            <Ionicons
              name="heart-half-outline"
              size={17}
              color={socialView === 'likes' ? Colors.white : Colors.primary}
            />
            <Text numberOfLines={1} style={[styles.socialViewText, socialView === 'likes' && styles.socialViewTextActive]}>
              Likes{receivedLikes.length + sentLikes.length > 0 ? ` (${receivedLikes.length + sentLikes.length})` : ''}
            </Text>
          </TouchableOpacity>
        </View>
      </LinearGradient>

      <View style={[styles.contentArea, socialView === 'discover' && modoSocial && styles.discoveryContentArea]}>
        {socialView === 'discover' && floatingLikeVisible && topReceivedLike ? (
          <TouchableOpacity style={styles.floatingLikeCard} activeOpacity={0.9} onPress={() => handleOpenLikesView('received')}>
            {topReceivedLike.foto_url ? (
              <Image source={{ uri: topReceivedLike.foto_url }} style={styles.incomingLikeAvatar} contentFit="cover" />
            ) : (
              <LinearGradient colors={['#EC4899', '#8B5CF6']} style={styles.incomingLikeAvatarFallback}>
                <Text style={styles.incomingLikeAvatarLetter}>{topReceivedLike.nombre.charAt(0).toUpperCase()}</Text>
              </LinearGradient>
            )}
            <View style={styles.incomingLikeTextWrap}>
              <Text style={styles.incomingLikeTitle} numberOfLines={1}>
                {receivedLikeTitle}
              </Text>
              <Text style={styles.incomingLikeSubtitle} numberOfLines={1}>
                {receivedLikeSubtitle}
              </Text>
            </View>
            <Ionicons name="heart" size={18} color="#E11D48" />
          </TouchableOpacity>
        ) : null}

        {latestAccountNotification ? (
          <TouchableOpacity
            style={[
              styles.accountCoverNoticeCard,
              latestAccountIsRequest && styles.accountCoverNoticeCardRequest,
              latestAccountIsApprovedStripe && styles.accountCoverNoticeCardPayment,
            ]}
            activeOpacity={latestAccountIsActionable ? 0.86 : 1}
            onPress={latestAccountIsApprovedStripe || latestAccountExitPass ? handleAccountNotificationPress : undefined}
            disabled={!latestAccountIsActionable}
          >
            <View
              style={[
                styles.accountCoverNoticeIcon,
                latestAccountNotification.type === 'social_gift_received' && { backgroundColor: '#FCE7F3' },
                latestAccountIsRequest && { backgroundColor: '#DBEAFE' },
                latestAccountIsApprovedStripe && { backgroundColor: '#FEF3C7' },
              ]}
            >
              <Ionicons
                name={
                  latestAccountNotification.type === 'social_gift_received'
                    ? 'gift-outline'
                    : latestAccountIsRequest
                      ? 'help-circle-outline'
                      : latestAccountIsApprovedStripe
                        ? 'card-outline'
                        : 'receipt-outline'
                }
                size={20}
                color={
                  latestAccountNotification.type === 'social_gift_received'
                    ? '#BE185D'
                    : latestAccountIsRequest
                      ? '#1D4ED8'
                      : latestAccountIsApprovedStripe
                        ? '#B45309'
                        : '#047857'
                }
              />
            </View>
            <View style={styles.accountCoverNoticeText}>
              <Text style={styles.accountCoverNoticeTitle} numberOfLines={1}>
                {latestAccountNotification.title || 'Cuenta cubierta'}
              </Text>
              <Text style={styles.accountCoverNoticeBody} numberOfLines={2}>
                {latestAccountNotification.body || 'Tu consumo fue cubierto por otro comensal.'}
              </Text>
              {latestAccountIsRequest ? (
                <View style={styles.accountCoverActions}>
                  <TouchableOpacity
                    style={[styles.accountCoverActionButton, styles.accountCoverRejectButton]}
                    activeOpacity={0.82}
                    disabled={accountNotificationBusyId === latestAccountCoverId}
                    onPress={() => handleRespondAccountCoverRequest('reject')}
                  >
                    <Text style={styles.accountCoverRejectText}>Rechazar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.accountCoverActionButton, styles.accountCoverAcceptButton]}
                    activeOpacity={0.82}
                    disabled={accountNotificationBusyId === latestAccountCoverId}
                    onPress={() => handleRespondAccountCoverRequest('accept')}
                  >
                    {accountNotificationBusyId === latestAccountCoverId ? (
                      <ActivityIndicator size="small" color={Colors.white} />
                    ) : (
                      <Text style={styles.accountCoverAcceptText}>Aceptar</Text>
                    )}
                  </TouchableOpacity>
                </View>
              ) : null}
            </View>
            {latestAccountExitPass ? (
              <Ionicons name="qr-code-outline" size={20} color="#047857" />
            ) : latestAccountIsApprovedStripe ? (
              <Ionicons name="chevron-forward" size={20} color="#B45309" />
            ) : null}
          </TouchableOpacity>
        ) : null}

        {profileLoading ? (
          <View style={styles.centerStateCard}>
            <ActivityIndicator size="large" color={Colors.primary} />
            <Text style={styles.centerStateTitle}>Preparando tu espacio social</Text>
            <Text style={styles.centerStateText}>Estamos cargando tu perfil y los datos de la sucursal.</Text>
          </View>
        ) : socialView === 'matches' ? (
          renderMatchesView()
        ) : socialView === 'likes' ? (
          renderLikesView()
        ) : modoSocial ? (
          renderCurrentDiner()
        ) : (
          <View style={styles.centerStateCard}>
            <Ionicons name="people-outline" size={72} color={Colors.textMuted} />
            <Text style={styles.centerStateTitle}>Conoce a otros comensales</Text>
            <Text style={styles.centerStateText}>
              Activa el modo social para descubrir quién más está disfrutando de este restaurante.
            </Text>

            <TouchableOpacity style={styles.primaryButton} onPress={() => updateSocialStatus(true)} activeOpacity={0.85}>
              {statusUpdating ? (
                <ActivityIndicator size="small" color={Colors.white} />
              ) : (
                <>
                  <Ionicons name="eye-outline" size={18} color={Colors.white} />
                  <Text style={styles.primaryButtonText}>Activar modo social</Text>
                </>
              )}
            </TouchableOpacity>

            <TouchableOpacity style={styles.secondaryButton} onPress={() => setModalVisible(true)} activeOpacity={0.8}>
              <Ionicons name="create-outline" size={18} color={Colors.primary} />
              <Text style={styles.secondaryButtonText}>
                {hasCompleteProfile ? 'Editar mi perfil social' : 'Completar mi perfil'}
              </Text>
            </TouchableOpacity>
          </View>
        )}
      </View>

      <Modal visible={modalVisible} transparent animationType="slide" onRequestClose={() => setModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={() => setModalVisible(false)} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Perfil social</Text>
              <TouchableOpacity onPress={() => setModalVisible(false)} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView
              contentContainerStyle={styles.modalContent}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
            >
              <View style={styles.profileGalleryBlock}>
                <View style={styles.galleryHeader}>
                  <View>
                    <Text style={styles.galleryTitle}>Fotos</Text>
                    <Text style={styles.helperText}>La primera foto será tu portada social.</Text>
                  </View>
                  <Text style={styles.galleryCount}>{socialPhotos.length}/6</Text>
                </View>

                <View style={styles.photoGrid}>
                  {socialPhotos.map((photo, index) => (
                    <View key={`${photo}-${index}`} style={styles.galleryPhotoSlot}>
                      <Image source={{ uri: photo }} style={styles.galleryPhotoImage} contentFit="cover" cachePolicy="disk" />

                      {index === 0 ? (
                        <View style={styles.primaryBadge}>
                          <Text style={styles.primaryBadgeText}>Principal</Text>
                        </View>
                      ) : (
                        <TouchableOpacity
                          style={styles.makePrimaryButton}
                          activeOpacity={0.82}
                          onPress={() => handleSetPrimaryPhoto(photo)}
                          disabled={uploading}
                        >
                          <Ionicons name="star-outline" size={15} color={Colors.white} />
                        </TouchableOpacity>
                      )}

                      <TouchableOpacity
                        style={styles.deletePhotoButton}
                        activeOpacity={0.82}
                        onPress={() => handleDeletePhoto(photo)}
                        disabled={uploading}
                      >
                        <Ionicons name="trash-outline" size={15} color={Colors.white} />
                      </TouchableOpacity>
                    </View>
                  ))}

                  {socialPhotos.length < 6 ? (
                    <TouchableOpacity
                      activeOpacity={0.86}
                      onPress={handlePickImage}
                      disabled={uploading}
                      style={[styles.galleryPhotoSlot, styles.addPhotoSlot]}
                    >
                      {uploading ? (
                        <ActivityIndicator size="small" color={Colors.primary} />
                      ) : (
                        <>
                          <Ionicons name="add" size={28} color={Colors.primary} />
                          <Text style={styles.addPhotoText}>Agregar</Text>
                        </>
                      )}
                    </TouchableOpacity>
                  ) : null}

                  {Array.from({ length: Math.max(0, 6 - socialPhotos.length - (socialPhotos.length < 6 ? 1 : 0)) }).map(
                    (_, index) => (
                      <View key={`empty-${index}`} style={[styles.galleryPhotoSlot, styles.emptyPhotoSlot]} />
                    )
                  )}
                </View>
              </View>

              <InputField
                label="Nombre"
                value={form.nombre}
                onChangeText={(value) => updateField('nombre', value.slice(0, 80))}
                placeholder="Cómo quieres aparecer"
                autoCapitalize="words"
                maxLength={80}
              />

              <InputField
                label="Edad"
                value={form.edad}
                onChangeText={(value) => updateField('edad', value)}
                keyboardType="number-pad"
                placeholder="Tu edad"
                maxLength={3}
              />

              <ChoiceField
                label="Sexualidad"
                value={form.sexualidad}
                placeholder="Selecciona tu sexualidad"
                options={SEXUALITY_OPTIONS}
                onSelect={(value) => updateField('sexualidad', value)}
              />

              <ChoiceField
                label="Género"
                value={form.genero}
                placeholder="Selecciona tu género"
                options={GENDER_OPTIONS}
                onSelect={(value) => updateField('genero', value)}
              />

              <ChoiceField
                label="Qué buscas"
                value={form.queBusca}
                placeholder="Cuéntale a otros qué buscas"
                options={LOOKING_FOR_OPTIONS}
                onSelect={(value) => updateField('queBusca', value)}
              />

              <InputField
                label="Descripción"
                value={form.biografia}
                onChangeText={(value) => updateField('biografia', value)}
                placeholder="Describe tu vibra, tu plan favorito o lo que te gusta platicar."
                multiline
                numberOfLines={4}
                textAlignVertical="top"
                style={styles.textAreaInput}
              />

              <View style={styles.fieldBlock}>
                <Text style={styles.fieldLabel}>Intereses</Text>
                <Text style={styles.helperText}>Opcional. Toca los temas que mejor te representen.</Text>
                <View style={styles.interestWrap}>
                  {interestOptions.map((interest) => {
                    const active = selectedInterests.includes(interest);
                    return (
                      <TouchableOpacity
                        key={interest}
                        activeOpacity={0.85}
                        onPress={() => toggleInterest(interest)}
                        style={[styles.editorInterestChip, active && styles.editorInterestChipActive]}
                      >
                        <Ionicons
                          name={active ? 'checkmark-circle' : 'ellipse-outline'}
                          size={16}
                          color={active ? Colors.white : Colors.textMuted}
                        />
                        <Text style={[styles.editorInterestText, active && styles.editorInterestTextActive]}>
                          {interest}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>

              <InputField
                label="Instagram"
                value={form.instagram}
                onChangeText={(value) => updateField('instagram', value)}
                placeholder="@usuario"
                autoCapitalize="none"
              />

              <InputField
                label="TikTok"
                value={form.tiktok}
                onChangeText={(value) => updateField('tiktok', value)}
                placeholder="@usuario"
                autoCapitalize="none"
              />
            </ScrollView>

            <TouchableOpacity
              style={[styles.saveButton, saving && styles.saveButtonDisabled]}
              activeOpacity={0.85}
              onPress={handleSaveProfile}
              disabled={saving}
            >
              {saving ? (
                <ActivityIndicator size="small" color={Colors.white} />
              ) : (
                <>
                  <Ionicons name="save-outline" size={18} color={Colors.white} />
                  <Text style={styles.saveButtonText}>Guardar perfil social</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      <Modal visible={consentModalVisible} transparent animationType="slide" onRequestClose={handleCancelSocialConsent}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={handleCancelSocialConsent} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Antes de activar tu perfil social</Text>
              <TouchableOpacity onPress={handleCancelSocialConsent} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.consentContent} showsVerticalScrollIndicator={false}>
              <View style={styles.consentIconWrap}>
                <Ionicons name="shield-checkmark-outline" size={34} color={Colors.primary} />
              </View>

              <Text style={styles.consentText}>
                Al activar el modo social, otros comensales activos de esta sucursal podrán ver la información que
                compartas en tu perfil: nombre, fotos, edad, genero, sexualidad, descripcion, intereses, que buscas y
                tu mesa o sucursal actual.
              </Text>

              <Text style={styles.consentText}>
                Estos datos se usaran solo para las funciones sociales de la app, como descubrir comensales y enviar
                regalos. Puedes apagar el modo social cuando quieras.
              </Text>

              <TouchableOpacity style={styles.privacyNoticeButton} activeOpacity={0.82} onPress={openPrivacyNotice}>
                <Ionicons name="document-text-outline" size={18} color={Colors.primary} />
                <Text style={styles.privacyNoticeText}>Ver aviso de privacidad</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.consentCheckRow}
                activeOpacity={0.85}
                onPress={() => setSocialConsentChecked((current) => !current)}
              >
                <View style={[styles.consentCheckbox, socialConsentChecked && styles.consentCheckboxActive]}>
                  {socialConsentChecked ? <Ionicons name="checkmark" size={18} color={Colors.white} /> : null}
                </View>
                <Text style={styles.consentCheckText}>
                  Acepto compartir mis datos personales para usar el modo social de Amare.
                </Text>
              </TouchableOpacity>
            </ScrollView>

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionGhostButton]}
                activeOpacity={0.85}
                onPress={handleCancelSocialConsent}
                disabled={statusUpdating}
              >
                <Text style={styles.modalActionGhostButtonText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[
                  styles.modalActionButton,
                  styles.modalActionPrimaryButton,
                  (!socialConsentChecked || statusUpdating) && styles.saveButtonDisabled,
                ]}
                activeOpacity={0.85}
                onPress={handleAcceptSocialConsent}
                disabled={!socialConsentChecked || statusUpdating}
              >
                {statusUpdating ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Text style={styles.saveButtonText}>Acepto y activar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal
        visible={mesaModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => {
          if (!statusUpdating) {
            setPendingActivationAfterBranch(false);
            setMesaModalVisible(false);
          }
        }}
      >
        <View style={styles.modalOverlay}>
          <Pressable
            style={styles.modalBackdrop}
            onPress={() => {
              if (!statusUpdating) {
                setPendingActivationAfterBranch(false);
                setMesaModalVisible(false);
              }
            }}
          />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Tu mesa</Text>
              <TouchableOpacity
                onPress={() => {
                  if (!statusUpdating) {
                    setPendingActivationAfterBranch(false);
                    setMesaModalVisible(false);
                  }
                }}
                style={styles.closeButton}
                activeOpacity={0.8}
                disabled={statusUpdating}
              >
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <Text style={styles.helperText}>
              Comparte tu número de mesa para ubicar mejor tu perfil en la sucursal y dejar listo el flujo de regalos
              hacia el panel de meseros.
            </Text>

            {mesaOptionsLoading ? (
              <View style={styles.giftLoadingWrap}>
                <ActivityIndicator size="large" color={Colors.primary} />
                <Text style={styles.giftLoadingText}>Buscando mesas disponibles...</Text>
              </View>
            ) : mesaOptions.length > 0 ? (
              <ChoiceField
                label="Mesa"
                value={mesaInput}
                placeholder="Selecciona tu mesa"
                options={mesaOptions}
                onSelect={(value) => setMesaInput(value)}
              />
            ) : (
              <>
                <InputField
                  label="Número de mesa"
                  value={mesaInput}
                  onChangeText={(value) => setMesaInput(value.slice(0, 20))}
                  placeholder="Ej. 12"
                  autoCapitalize="characters"
                  autoCorrect={false}
                  returnKeyType="done"
                  onSubmitEditing={handleConfirmMesa}
                />
                <Text style={styles.helperText}>
                  Si no vemos las mesas desde la sucursal, puedes escribirla manualmente y seguir usando el modo social.
                </Text>
              </>
            )}

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionGhostButton]}
                activeOpacity={0.85}
                onPress={() => {
                  setPendingActivationAfterBranch(false);
                  setMesaModalVisible(false);
                }}
                disabled={statusUpdating}
              >
                <Text style={styles.modalActionGhostButtonText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionPrimaryButton, statusUpdating && styles.saveButtonDisabled]}
                activeOpacity={0.85}
                onPress={handleConfirmMesa}
                disabled={statusUpdating}
              >
                {statusUpdating ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Text style={styles.saveButtonText}>Activar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={detailsVisible} transparent animationType="slide" onRequestClose={handleCloseDinerDetails}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={handleCloseDinerDetails} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Perfil del comensal</Text>
              <View style={styles.modalHeaderActions}>
                {detailDiner && canUseSocialActions ? (
                  <TouchableOpacity
                    onPress={() => openGiftSelector()}
                    style={styles.headerGiftButton}
                    activeOpacity={0.8}
                    accessibilityLabel="Enviar regalo"
                    accessibilityRole="button"
                  >
                    <Ionicons name="gift-outline" size={20} color={Colors.primary} />
                  </TouchableOpacity>
                ) : null}
                <TouchableOpacity onPress={handleCloseDinerDetails} style={styles.closeButton} activeOpacity={0.8}>
                  <Ionicons name="close" size={24} color={Colors.textSecondary} />
                </TouchableOpacity>
              </View>
            </View>

            {detailDiner ? (
              <ScrollView contentContainerStyle={styles.modalContent} showsVerticalScrollIndicator={false}>
                <View style={styles.detailHeroCard}>
                  <View style={styles.detailHeroImageWrap}>
                    {detailPhotos.length > 0 ? (
                      <>
                        <ScrollView
                          ref={detailPhotoScrollRef}
                          horizontal
                          pagingEnabled
                          showsHorizontalScrollIndicator={false}
                          style={styles.detailPhotoCarousel}
                          onMomentumScrollEnd={handleDetailPhotoScroll}
                        >
                          {detailPhotos.map((photo, index) => (
                            <Image
                              key={`${photo}-${index}`}
                              source={{ uri: photo }}
                              style={[styles.detailHeroImage, { width: detailImageWidth }]}
                              contentFit="cover"
                              cachePolicy="disk"
                            />
                          ))}
                        </ScrollView>

                        {detailPhotos.length > 1 ? (
                          <>
                            <TouchableOpacity
                              style={[styles.detailPhotoArrow, styles.detailPhotoArrowLeft]}
                              activeOpacity={0.82}
                              onPress={() => scrollDetailPhoto('prev')}
                              accessibilityRole="button"
                              accessibilityLabel="Foto anterior"
                            >
                              <Ionicons name="chevron-back" size={24} color={Colors.white} />
                            </TouchableOpacity>
                            <TouchableOpacity
                              style={[styles.detailPhotoArrow, styles.detailPhotoArrowRight]}
                              activeOpacity={0.82}
                              onPress={() => scrollDetailPhoto('next')}
                              accessibilityRole="button"
                              accessibilityLabel="Siguiente foto"
                            >
                              <Ionicons name="chevron-forward" size={24} color={Colors.white} />
                            </TouchableOpacity>
                            <View style={styles.detailPhotoCount}>
                              <Ionicons name="images-outline" size={14} color={Colors.white} />
                              <Text style={styles.detailPhotoCountText}>
                                {detailPhotoIndex + 1}/{detailPhotos.length}
                              </Text>
                            </View>
                          </>
                        ) : null}
                      </>
                    ) : (
                      <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.detailHeroFallback}>
                        <Text style={styles.dinerImageFallbackLetter}>{detailDiner.nombre[0]?.toUpperCase() ?? '?'}</Text>
                      </LinearGradient>
                    )}

                    {isLikedOrMatched(detailDiner) ? (
                      <View style={styles.detailLikedBadge}>
                        <Ionicons name="heart" size={16} color={Colors.white} />
                        <Text style={styles.likedBadgeText}>
                          {detailDiner.relationship_status === 'matched' ? 'Match' : 'Like enviado'}
                        </Text>
                      </View>
                    ) : null}
                  </View>
                  {renderDinerDetailsContent(detailDiner)}
                </View>
              </ScrollView>
            ) : null}
          </View>
        </View>
      </Modal>

      <Modal visible={coverAccountVisible} transparent animationType="slide" onRequestClose={closeCoverAccountModal}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={coverAccountSending ? undefined : closeCoverAccountModal} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Cubrir cuenta</Text>
              <TouchableOpacity
                onPress={closeCoverAccountModal}
                style={styles.closeButton}
                activeOpacity={0.8}
                disabled={coverAccountSending}
              >
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.coverAccountContent} showsVerticalScrollIndicator={false}>
              {coverAccountLoading ? (
                <View style={styles.coverAccountLoading}>
                  <ActivityIndicator color={Colors.primary} />
                  <Text style={styles.centerStateText}>Consultando consumo...</Text>
                </View>
              ) : coverAccountResult?.available && coverAccountResult.account ? (
                <>
                  <View style={styles.coverAccountHero}>
                    <View style={styles.coverAccountIcon}>
                      <Ionicons name="receipt-outline" size={24} color={Colors.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.coverAccountTitle}>
                        Cuenta de {coverAccountResult.recipient?.nombre ?? coverAccountDiner?.nombre ?? 'comensal'}
                      </Text>
                      <Text style={styles.coverAccountSubtitle}>
                        {coverAccountResult.recipient?.mesa ? `Mesa ${coverAccountResult.recipient.mesa}` : 'Mesa del comensal'}
                      </Text>
                    </View>
                    <Text style={styles.coverAccountTotal}>${coverAccountResult.account.total_mxn.toFixed(2)}</Text>
                  </View>

                  <View style={styles.coverAccountItems}>
                    {coverAccountResult.account.items.slice(0, 4).map((item) => (
                      <View key={`${item.pedido_id}-${item.id}`} style={styles.coverAccountItemRow}>
                        <Text style={styles.coverAccountItemName} numberOfLines={1}>
                          {item.cantidad}x {item.nombre}
                        </Text>
                        <Text style={styles.coverAccountItemPrice}>${item.subtotal.toFixed(2)}</Text>
                      </View>
                    ))}
                    {coverAccountResult.account.items.length > 4 ? (
                      <Text style={styles.coverAccountMoreText}>
                        +{coverAccountResult.account.items.length - 4} productos mas
                      </Text>
                    ) : null}
                  </View>

                  <View style={styles.giftPaymentModes}>
                    <TouchableOpacity
                      activeOpacity={0.86}
                      onPress={() => setCoverAccountMode('account')}
                      style={[
                        styles.giftPaymentModeCard,
                        coverAccountMode === 'account' && styles.giftPaymentModeCardActive,
                      ]}
                    >
                      <Ionicons name="add-circle-outline" size={18} color={coverAccountMode === 'account' ? Colors.primary : Colors.textSecondary} />
                      <Text style={[styles.giftPaymentModeTitle, coverAccountMode === 'account' && styles.giftPaymentModeTitleActive]}>
                        A mi cuenta
                      </Text>
                      <Text style={styles.giftPaymentModeText}>Primero debe aceptar.</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      activeOpacity={0.86}
                      onPress={() => stripeAvailable && setCoverAccountMode('stripe')}
                      style={[
                        styles.giftPaymentModeCard,
                        coverAccountMode === 'stripe' && styles.giftPaymentModeCardActive,
                        !stripeAvailable && styles.giftPaymentModeCardDisabled,
                      ]}
                      disabled={!stripeAvailable}
                    >
                      <Ionicons name="card-outline" size={18} color={coverAccountMode === 'stripe' ? Colors.primary : Colors.textSecondary} />
                      <Text style={[styles.giftPaymentModeTitle, coverAccountMode === 'stripe' && styles.giftPaymentModeTitleActive]}>
                        Pagar ahora
                      </Text>
                      <Text style={styles.giftPaymentModeText}>Se habilita si acepta.</Text>
                    </TouchableOpacity>
                  </View>

                  <Text style={styles.coverAccountApprovalNote}>
                    Enviaremos una solicitud al comensal. El pago solo se procesa si acepta.
                  </Text>

                  <TouchableOpacity
                    style={[styles.giftSendButton, coverAccountSending && styles.saveButtonDisabled]}
                    activeOpacity={0.86}
                    disabled={coverAccountSending}
                    onPress={() => handleCoverAccountSubmit(coverAccountMode)}
                  >
                    {coverAccountSending ? (
                      <ActivityIndicator size="small" color={Colors.white} />
                    ) : (
                      <Ionicons name={coverAccountMode === 'stripe' ? 'card-outline' : 'add-circle-outline'} size={18} color={Colors.white} />
                    )}
                    <Text style={styles.giftSendButtonText}>
                      {coverAccountMode === 'stripe'
                        ? `Solicitar permiso para pagar $${coverAccountResult.account.total_mxn.toFixed(2)}`
                        : `Solicitar agregar $${coverAccountResult.account.total_mxn.toFixed(2)} a mi cuenta`}
                    </Text>
                  </TouchableOpacity>
                </>
              ) : (
                <View style={styles.coverAccountEmpty}>
                  <Ionicons name="checkmark-circle-outline" size={42} color={Colors.success || '#059669'} />
                  <Text style={styles.centerStateTitle}>Sin consumo pendiente</Text>
                  <Text style={styles.centerStateText}>
                    {coverAccountResult?.message || 'Este comensal no tiene productos pendientes por cubrir.'}
                  </Text>
                </View>
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>

      <Modal
        visible={Boolean(approvedCoverPayment)}
        transparent
        animationType="slide"
        onRequestClose={() => {
          if (!approvedCoverPaying) {
            setApprovedCoverPayment(null);
            setApprovedCoverCardComplete(false);
          }
        }}
      >
        <View style={styles.modalOverlay}>
          <Pressable
            style={styles.modalBackdrop}
            onPress={
              approvedCoverPaying
                ? undefined
                : () => {
                    setApprovedCoverPayment(null);
                    setApprovedCoverCardComplete(false);
                  }
            }
          />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Pagar cuenta aceptada</Text>
              <TouchableOpacity
                onPress={() => {
                  setApprovedCoverPayment(null);
                  setApprovedCoverCardComplete(false);
                }}
                style={styles.closeButton}
                activeOpacity={0.8}
                disabled={approvedCoverPaying}
              >
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <View style={styles.approvedCoverContent}>
              <View style={styles.coverAccountHero}>
                <View style={styles.coverAccountIcon}>
                  <Ionicons name="card-outline" size={24} color={Colors.primary} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.coverAccountTitle}>
                    {approvedCoverPayment?.payload?.covered_name
                      ? `Cuenta de ${String(approvedCoverPayment.payload.covered_name)}`
                      : 'Cuenta autorizada'}
                  </Text>
                  <Text style={styles.coverAccountSubtitle}>El comensal acepto que pagues su cuenta.</Text>
                </View>
                <Text style={styles.coverAccountTotal}>
                  ${Number(approvedCoverPayment?.payload?.amount_mxn ?? 0).toFixed(2)}
                </Text>
              </View>

              {stripeAvailable ? (
                <View style={styles.giftStripeBox}>
                  <CardField
                    postalCodeEnabled={false}
                    placeholders={{ number: '4242 4242 4242 4242' }}
                    cardStyle={{
                      backgroundColor: '#FFFFFF',
                      textColor: '#111827',
                      borderColor: '#D8DDE8',
                      borderWidth: 1,
                      borderRadius: 12,
                    }}
                    style={styles.giftStripeCardField}
                    onCardChange={(details) => setApprovedCoverCardComplete(Boolean(details.complete))}
                  />
                </View>
              ) : (
                <Text style={styles.giftUnavailableText}>Configura EXPO_PUBLIC_STRIPE_KEY para pagar ahora.</Text>
              )}

              <TouchableOpacity
                style={[styles.giftSendButton, approvedCoverPaying && styles.saveButtonDisabled]}
                activeOpacity={0.86}
                disabled={approvedCoverPaying || !stripeAvailable}
                onPress={handleApprovedCoverStripePayment}
              >
                {approvedCoverPaying ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Ionicons name="card-outline" size={18} color={Colors.white} />
                )}
                <Text style={styles.giftSendButtonText}>Confirmar pago</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={filtersVisible} transparent animationType="slide" onRequestClose={() => setFiltersVisible(false)}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={() => setFiltersVisible(false)} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Filtrar comensales</Text>
              <TouchableOpacity onPress={() => setFiltersVisible(false)} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.modalContent} showsVerticalScrollIndicator={false}>
              <Text style={styles.helperText}>
                Ajusta edad, género o sexualidad para ver perfiles más cercanos a lo que buscas.
              </Text>

              <View style={styles.filterRow}>
                <View style={styles.filterHalf}>
                  <InputField
                    label="Edad mínima"
                    value={filters.edadMin}
                    onChangeText={(value) => updateFilter('edadMin', value.replace(/[^0-9]/g, ''))}
                    placeholder="18"
                    keyboardType="number-pad"
                  />
                </View>

                <View style={styles.filterHalf}>
                  <InputField
                    label="Edad máxima"
                    value={filters.edadMax}
                    onChangeText={(value) => updateFilter('edadMax', value.replace(/[^0-9]/g, ''))}
                    placeholder="35"
                    keyboardType="number-pad"
                  />
                </View>
              </View>

              <ChoiceField
                label="Género"
                value={filters.genero}
                placeholder="Todos"
                options={GENDER_OPTIONS}
                onSelect={(value) => updateFilter('genero', value)}
              />

              <ChoiceField
                label="Sexualidad"
                value={filters.sexualidad}
                placeholder="Todas"
                options={SEXUALITY_OPTIONS}
                onSelect={(value) => updateFilter('sexualidad', value)}
              />
            </ScrollView>

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={styles.filterGhostButton}
                activeOpacity={0.85}
                onPress={() => setFilters(EMPTY_FILTERS)}
              >
                <Text style={styles.filterGhostButtonText}>Limpiar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.saveButton}
                activeOpacity={0.85}
                onPress={() => {
                  setCurrentIndex(0);
                  setFiltersVisible(false);
                }}
              >
                <Ionicons name="options-outline" size={18} color={Colors.white} />
                <Text style={styles.saveButtonText}>Aplicar filtros</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={likeGiftPromptVisible} transparent animationType="fade" onRequestClose={closeLikeGiftPrompt}>
        <View style={styles.promptOverlay}>
          <Pressable style={styles.promptBackdrop} onPress={closeLikeGiftPrompt} />

          <View style={styles.likeGiftPromptCard}>
            <View style={styles.likeGiftPromptIcon}>
              <Ionicons name={likeGiftPromptMatched ? 'sparkles' : 'heart'} size={28} color={Colors.white} />
            </View>

            <Text style={styles.likeGiftPromptTitle}>
              {likeGiftPromptMatched ? '¡Es match!' : 'Like enviado'}
            </Text>
            <Text style={styles.likeGiftPromptText}>
              {likeGiftPromptDiner
                ? likeGiftPromptMatched
                  ? `${likeGiftPromptDiner.nombre} también te dio like. ¿Quieres enviarle un regalo?`
                  : `¿Quieres enviarle un regalo a ${likeGiftPromptDiner.nombre}?`
                : '¿Quieres enviar un regalo ahora?'}
            </Text>

            <View style={styles.likeGiftPromptActions}>
              <TouchableOpacity style={styles.likeGiftLaterButton} activeOpacity={0.85} onPress={closeLikeGiftPrompt}>
                <Text style={styles.likeGiftLaterText}>Más tarde</Text>
              </TouchableOpacity>

              <TouchableOpacity style={styles.likeGiftSendButton} activeOpacity={0.86} onPress={handleGiftAfterLike}>
                <Text style={styles.likeGiftSendText}>Enviar regalo</Text>
                <Ionicons name="gift-outline" size={18} color={Colors.white} />
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={giftsVisible} transparent animationType="slide" onRequestClose={closeGiftSelector}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={closeGiftSelector} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Enviar regalo</Text>
              <TouchableOpacity onPress={closeGiftSelector} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <Text style={styles.giftModalSubtitle}>
              {detailDiner ? `Elige un detalle para ${detailDiner.nombre}.` : 'Elige un detalle para este comensal.'}
            </Text>

            <ScrollView contentContainerStyle={styles.giftList} showsVerticalScrollIndicator={false}>
              {giftProductsLoading ? (
                <View style={styles.giftLoadingWrap}>
                  <ActivityIndicator size="large" color={Colors.primary} />
                  <Text style={styles.giftLoadingText}>Cargando regalos disponibles...</Text>
                </View>
              ) : giftProducts.length === 0 ? (
                <View style={styles.giftLoadingWrap}>
                  <Ionicons name="gift-outline" size={42} color={Colors.textMuted} />
                  <Text style={styles.giftLoadingText}>No encontramos regalos disponibles por ahora.</Text>
                </View>
              ) : (
                giftProducts.map((gift) => {
                  const active = gift.id === selectedGiftId;
                  return (
                    <TouchableOpacity
                      key={gift.id}
                      activeOpacity={0.85}
                      onPress={() => setSelectedGiftId(gift.id)}
                      style={[styles.giftItem, active && styles.giftItemActive]}
                    >
                      <View style={[styles.giftIconWrap, { backgroundColor: `${gift.color ?? Colors.primary}18` }]}>
                        {gift.imagen ? (
                          <Image source={{ uri: gift.imagen }} style={styles.giftImage} contentFit="cover" cachePolicy="disk" />
                        ) : (
                          <Ionicons name={(gift.icono as never) || 'gift'} size={22} color={gift.color ?? Colors.primary} />
                        )}
                      </View>

                      <View style={styles.giftInfo}>
                        <Text style={styles.giftName}>{gift.nombre}</Text>
                        {gift.descripcion ? <Text style={styles.giftDescription}>{gift.descripcion}</Text> : null}
                      </View>

                      <View style={styles.giftMeta}>
                        <Text style={styles.giftPrice}>${gift.precio.toFixed(2)}</Text>
                        <Ionicons
                          name={active ? 'checkmark-circle' : 'ellipse-outline'}
                          size={20}
                          color={active ? Colors.primary : Colors.textMuted}
                        />
                      </View>
                    </TouchableOpacity>
                  );
                })
              )}
            </ScrollView>

            {selectedGift ? (
              <View style={styles.giftPaymentSection}>
                <View style={styles.giftPaymentModes}>
                  <TouchableOpacity
                    activeOpacity={0.85}
                    onPress={() => selectGiftCheckoutMode('account')}
                    style={[styles.giftPaymentModeCard, giftCheckoutMode === 'account' && styles.giftPaymentModeCardActive]}
                  >
                    <Ionicons name="receipt-outline" size={18} color={giftCheckoutMode === 'account' ? Colors.primary : Colors.textSecondary} />
                    <View style={styles.giftPaymentModeTextWrap}>
                      <Text style={[styles.giftPaymentModeTitle, giftCheckoutMode === 'account' && styles.giftPaymentModeTitleActive]}>
                        Agregar a mi cuenta
                      </Text>
                      <Text style={styles.giftPaymentModeDescription}>
                        Se suma a tu mesa y lo pagas al cerrar la cuenta.
                      </Text>
                    </View>
                  </TouchableOpacity>

                  <TouchableOpacity
                    activeOpacity={0.85}
                    onPress={() => stripeAvailable && selectGiftCheckoutMode('stripe')}
                    style={[
                      styles.giftPaymentModeCard,
                      giftCheckoutMode === 'stripe' && styles.giftPaymentModeCardActive,
                      !stripeAvailable && styles.giftPaymentModeCardDisabled,
                    ]}
                    disabled={!stripeAvailable}
                  >
                    <Ionicons name="card-outline" size={18} color={giftCheckoutMode === 'stripe' ? Colors.primary : Colors.textSecondary} />
                    <View style={styles.giftPaymentModeTextWrap}>
                      <Text style={[styles.giftPaymentModeTitle, giftCheckoutMode === 'stripe' && styles.giftPaymentModeTitleActive]}>
                        Pagar ahora
                      </Text>
                      <Text style={styles.giftPaymentModeDescription}>
                        Cobra el regalo al instante con Stripe.
                      </Text>
                    </View>
                  </TouchableOpacity>

                  <TouchableOpacity
                    activeOpacity={0.85}
                    onPress={() => selectGiftCheckoutMode('wallet')}
                    style={[
                      styles.giftPaymentModeCard,
                      giftCheckoutMode === 'wallet' && styles.giftPaymentModeCardActive,
                      giftRewardsQuote && !giftRewardsQuote.can_pay && styles.giftPaymentModeCardDisabled,
                    ]}
                    disabled={Boolean(giftRewardsQuote && !giftRewardsQuote.can_pay)}
                  >
                    <Ionicons name="sparkles-outline" size={18} color={giftCheckoutMode === 'wallet' ? Colors.primary : Colors.textSecondary} />
                    <View style={styles.giftPaymentModeTextWrap}>
                      <Text style={[styles.giftPaymentModeTitle, giftCheckoutMode === 'wallet' && styles.giftPaymentModeTitleActive]}>
                        Saldo Amare
                      </Text>
                      <Text style={styles.giftPaymentModeDescription}>
                        Paga con tu prepago Amare y recibe 10% de descuento.
                      </Text>
                    </View>
                  </TouchableOpacity>
                </View>

                <View style={styles.giftPaymentBox}>
                  <View style={styles.giftPaymentHeading}>
                    <View>
                      <Text style={styles.giftPaymentLabel}>
                        {giftCheckoutMode === 'stripe'
                          ? 'Pago inmediato con Stripe'
                          : giftCheckoutMode === 'wallet'
                            ? 'Pago con Saldo Amare'
                            : 'Se carga a tu cuenta'}
                      </Text>
                      <Text style={styles.giftPaymentHint}>
                        {giftCheckoutMode === 'stripe'
                          ? 'Captura tu tarjeta para cobrar este regalo antes de enviarlo.'
                          : giftCheckoutMode === 'wallet'
                            ? 'Usa tu saldo prepago y recibe 10% de descuento en este regalo.'
                            : 'Pagarás este regalo al cerrar la cuenta de tu mesa.'}
                      </Text>
                    </View>
                    <Text style={styles.giftPaymentTotal}>
                      ${giftCheckoutMode === 'wallet' && giftRewardsQuote ? giftRewardsQuote.wallet_total.toFixed(2) : selectedGift.precio.toFixed(2)}
                    </Text>
                  </View>

                  {giftCheckoutMode === 'wallet' ? (
                    <View style={styles.giftRewardsSummary}>
                      <View style={styles.giftRewardsRow}>
                        <Text style={styles.giftRewardsLabel}>Saldo</Text>
                        <Text style={styles.giftRewardsValue}>${Number(giftRewardsWallet?.balance_mxn ?? giftRewardsQuote?.balance_mxn ?? 0).toFixed(2)}</Text>
                      </View>
                      <View style={styles.giftRewardsRow}>
                        <Text style={styles.giftRewardsLabel}>Descuento Amare</Text>
                        <Text style={styles.giftRewardsValue}>-${Number(giftRewardsQuote?.discount_amount ?? 0).toFixed(2)}</Text>
                      </View>
                      <View style={styles.giftRewardsRow}>
                        <Text style={styles.giftRewardsLabel}>Saldo a usar</Text>
                        <Text style={styles.giftRewardsValue}>${Number(giftRewardsQuote?.wallet_total ?? selectedGift.precio).toFixed(2)}</Text>
                      </View>
                      <View style={styles.giftRewardsToggle}>
                        <Text style={styles.giftRewardsLabel}>
                          Si no pagas con saldo, tus compras generan 5% del total en puntos.
                        </Text>
                      </View>
                      {giftRewardsQuote && !giftRewardsQuote.can_pay ? (
                        <Text style={styles.giftStripeWarning}>Tu Saldo Amare no alcanza para este regalo.</Text>
                      ) : null}
                    </View>
                  ) : null}

                  {giftCheckoutMode === 'stripe' ? (
                    stripeAvailable ? (
                      <View style={styles.giftStripeCardWrap}>
                        <CardField
                          postalCodeEnabled={false}
                          placeholders={{ number: '4242 4242 4242 4242' }}
                          cardStyle={{
                            backgroundColor: Colors.white,
                            textColor: Colors.text,
                            placeholderColor: Colors.textMuted,
                            borderRadius: 12,
                          }}
                          style={styles.giftStripeCardField}
                          onCardChange={(card) => setGiftCardComplete(Boolean(card.complete))}
                        />
                        <Text style={styles.giftStripeNote}>Pago seguro procesado por Stripe.</Text>
                      </View>
                    ) : (
                      <Text style={styles.giftStripeWarning}>
                        Configura EXPO_PUBLIC_STRIPE_KEY en la app y STRIPE_SECRET_KEY en el backend para habilitar esta opcion.
                      </Text>
                    )
                  ) : null}
                </View>
              </View>
            ) : null}

            <TouchableOpacity
              style={[styles.saveButton, !canSubmitGift && styles.saveButtonDisabled]}
              activeOpacity={0.85}
              onPress={handleSendGift}
              disabled={!canSubmitGift}
            >
              {giftSending ? <ActivityIndicator size="small" color={Colors.white} /> : <Ionicons name="gift-outline" size={18} color={Colors.white} />}
              <Text style={styles.saveButtonText}>
                {giftSending
                  ? giftCheckoutMode === 'stripe'
                    ? 'Procesando pago...'
                    : giftCheckoutMode === 'wallet'
                      ? 'Pagando con saldo...'
                      : 'Enviando...'
                  : giftCheckoutMode === 'stripe'
                    ? `Pagar y enviar${selectedGift ? ` · $${selectedGift.precio.toFixed(2)}` : ''}`
                    : giftCheckoutMode === 'wallet'
                      ? `Enviar con saldo${giftRewardsQuote ? ` · $${giftRewardsQuote.wallet_total.toFixed(2)}` : ''}`
                      : `Enviar y cargar a cuenta${selectedGift ? ` · $${selectedGift.precio.toFixed(2)}` : ''}`}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  heroBackground: {
    backgroundColor: Colors.background,
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 20,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 18,
  },
  iconButton: {
    width: 44,
    height: 44,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.86)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E7EAF0',
  },
  headerTextWrap: {
    flex: 1,
    paddingHorizontal: 14,
  },
  headerTitle: {
    fontSize: 34,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.9,
  },
  headerSubtitle: {
    marginTop: 2,
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '500',
  },
  statusCard: {
    backgroundColor: Colors.white,
    borderRadius: 24,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    ...Shadows.card,
  },
  statusTextBlock: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginRight: 12,
  },
  statusDot: {
    width: 9,
    height: 9,
    borderRadius: 4.5,
    marginTop: 5,
    marginRight: 9,
  },
  statusBody: {
    flex: 1,
  },
  statusTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.3,
  },
  statusSubtitle: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 19,
    color: Colors.textSecondary,
  },
  statusHint: {
    marginTop: 4,
    fontSize: 11,
    lineHeight: 15,
    color: Colors.textMuted,
  },
  statusSwitch: {
    transform: [{ scaleX: 0.64 }, { scaleY: 0.64 }],
    marginRight: -10,
  },
  currentTablePill: {
    marginTop: 10,
    backgroundColor: '#FFFFFFCC',
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'nowrap',
    gap: 10,
    marginTop: 10,
  },
  summaryPill: {
    flex: 1,
    minWidth: 0,
    minHeight: 38,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: '#FFFFFFCC',
    borderWidth: 1,
    borderColor: '#E7EAF0',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  summaryPillActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  summaryPillText: {
    flex: 1,
    fontSize: 11,
    fontWeight: '700',
    color: Colors.primary,
    textAlign: 'center',
  },
  summaryPillTextActive: {
    color: Colors.white,
  },
  socialViewSwitch: {
    marginTop: 8,
    borderRadius: 14,
    backgroundColor: '#FFFFFFB8',
    borderWidth: 1,
    borderColor: '#E7EAF0',
    padding: 3,
    flexDirection: 'row',
    gap: 3,
  },
  socialViewButton: {
    flex: 1,
    minHeight: 34,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 4,
    paddingHorizontal: 5,
  },
  socialViewButtonActive: {
    backgroundColor: Colors.primary,
  },
  socialViewText: {
    flexShrink: 1,
    fontSize: 10,
    fontWeight: '800',
    color: Colors.primary,
    textAlign: 'center',
  },
  socialViewTextActive: {
    color: Colors.white,
  },
  incomingLikeCard: {
    marginTop: 12,
    borderRadius: 18,
    backgroundColor: '#FFFFFFE8',
    borderWidth: 1,
    borderColor: '#F2D7E2',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  incomingLikeAvatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#E8EBF3',
  },
  incomingLikeAvatarFallback: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  incomingLikeAvatarLetter: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
  incomingLikeTextWrap: {
    flex: 1,
    minWidth: 0,
  },
  incomingLikeTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  incomingLikeSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  receivedLikesModalSubtitle: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  receivedLikesList: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 24,
    gap: 12,
  },
  contentArea: {
    flex: 1,
    position: 'relative',
    backgroundColor: Colors.background,
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  discoveryContentArea: {
    paddingHorizontal: 10,
    paddingBottom: 10,
  },
  floatingLikeCard: {
    position: 'absolute',
    top: 2,
    left: 0,
    right: 0,
    zIndex: 20,
    borderRadius: 18,
    backgroundColor: '#FFFFFFF2',
    borderWidth: 1,
    borderColor: '#F2D7E2',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    ...Shadows.card,
  },
  accountCoverNoticeCard: {
    marginTop: 10,
    marginBottom: 10,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#A7F3D0',
    backgroundColor: '#ECFDF5',
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    ...Shadows.card,
  },
  accountCoverNoticeCardRequest: {
    borderColor: '#BFDBFE',
    backgroundColor: '#EFF6FF',
  },
  accountCoverNoticeCardPayment: {
    borderColor: '#FDE68A',
    backgroundColor: '#FFFBEB',
  },
  accountCoverNoticeIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D1FAE5',
  },
  accountCoverNoticeText: {
    flex: 1,
    minWidth: 0,
  },
  accountCoverNoticeTitle: {
    color: '#065F46',
    fontSize: 13,
    fontWeight: '900',
  },
  accountCoverNoticeBody: {
    marginTop: 3,
    color: '#047857',
    fontSize: 12,
    lineHeight: 17,
    fontWeight: '700',
  },
  accountCoverActions: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  accountCoverActionButton: {
    minHeight: 34,
    borderRadius: 12,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  accountCoverRejectButton: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#BFDBFE',
  },
  accountCoverAcceptButton: {
    backgroundColor: '#2563EB',
  },
  accountCoverRejectText: {
    color: '#1D4ED8',
    fontSize: 12,
    fontWeight: '900',
  },
  accountCoverAcceptText: {
    color: Colors.white,
    fontSize: 12,
    fontWeight: '900',
  },
  centerStateCard: {
    flex: 1,
    backgroundColor: Colors.white,
    borderRadius: 30,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    marginTop: 10,
    paddingHorizontal: 26,
    paddingVertical: 30,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.card,
  },
  centerStateTitle: {
    marginTop: 18,
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text,
    textAlign: 'center',
    letterSpacing: -0.5,
  },
  centerStateText: {
    marginTop: 10,
    fontSize: 16,
    lineHeight: 24,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  primaryButton: {
    marginTop: 24,
    minHeight: 58,
    borderRadius: 22,
    backgroundColor: Colors.primary,
    paddingHorizontal: 22,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    ...Shadows.md,
  },
  primaryButtonText: {
    fontSize: 17,
    fontWeight: '800',
    color: Colors.white,
    letterSpacing: -0.3,
  },
  secondaryButton: {
    marginTop: 14,
    minHeight: 52,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  secondaryButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  carouselArea: {
    flex: 1,
    paddingTop: 0,
    paddingBottom: 0,
  },
  tinderCardContainer: {
    flex: 1,
    width: '100%',
    alignItems: 'center',
    position: 'relative',
  },
  tinderCard: {
    flex: 1,
    width: '100%',
    maxWidth: 480,
  },
  discoveryActions: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 18,
    zIndex: 12,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    maxWidth: 460,
    alignSelf: 'center',
  },
  discoveryActionCenter: {
    flex: 1,
    flexDirection: 'row',
    gap: 8,
  },
  discoveryArrowButton: {
    width: 50,
    minHeight: 50,
    borderRadius: 17,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: 'rgba(255,255,255,0.92)',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    ...Shadows.sm,
  },
  discoveryArrowButtonDisabled: {
    opacity: 0.45,
  },
  likeButton: {
    flex: 1,
    minHeight: 50,
    borderRadius: 17,
    backgroundColor: '#E11D48',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 6,
    ...Shadows.md,
  },
  discoveryGiftButton: {
    flex: 1,
    minHeight: 50,
    borderRadius: 17,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 6,
    ...Shadows.md,
  },
  discoveryButtonText: {
    flexShrink: 1,
    fontSize: 13,
    fontWeight: '800',
    color: Colors.white,
  },
  dinerCard: {
    flex: 1,
    borderRadius: 30,
    backgroundColor: Colors.background,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    overflow: 'hidden',
    ...Shadows.lg,
  },
  dinerImageWrap: {
    flex: 1,
    backgroundColor: '#E8EBF3',
  },
  dinerImage: {
    width: '100%',
    height: '100%',
  },
  dinerImageFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  likedBadge: {
    position: 'absolute',
    top: 14,
    right: 14,
    minHeight: 34,
    borderRadius: 999,
    backgroundColor: 'rgba(225, 29, 72, 0.92)',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    zIndex: 2,
  },
  likedBadgeText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.white,
  },
  detailLikedBadge: {
    position: 'absolute',
    top: 14,
    left: 14,
    minHeight: 34,
    borderRadius: 999,
    backgroundColor: 'rgba(225, 29, 72, 0.92)',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    zIndex: 5,
  },
  dinerImageOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-end',
    paddingHorizontal: 20,
    paddingVertical: 20,
  },
  dinerImageName: {
    fontSize: 28,
    fontWeight: '800',
    color: Colors.white,
    letterSpacing: -0.7,
  },
  dinerImageHint: {
    marginTop: 6,
    fontSize: 14,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.88)',
  },
  dinerImageFallbackLetter: {
    fontSize: 96,
    fontWeight: '800',
    color: Colors.white,
  },
  dinerPreview: {
    backgroundColor: Colors.background,
    paddingHorizontal: 22,
    paddingTop: 14,
    paddingBottom: 78,
  },
  previewEyebrow: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.accentDark,
    letterSpacing: -0.2,
  },
  previewMetaWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
    marginTop: 10,
  },
  dinerContent: {
    paddingHorizontal: 22,
    paddingVertical: 18,
  },
  dinerName: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.6,
  },
  dinerSubtitle: {
    marginTop: 4,
    fontSize: 16,
    fontWeight: '700',
    color: Colors.accentDark,
  },
  metaWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 16,
  },
  metaChip: {
    borderRadius: 999,
    backgroundColor: '#EEF2FF',
    paddingHorizontal: 14,
    minHeight: 34,
    paddingVertical: 7,
    alignItems: 'center',
    justifyContent: 'center',
  },
  metaChipInteractive: {
    flexDirection: 'row',
    gap: 5,
  },
  metaChipText: {
    fontSize: 14,
    lineHeight: 18,
    fontWeight: '700',
    color: Colors.primary,
  },
  descriptionText: {
    marginTop: 16,
    fontSize: 16,
    lineHeight: 23,
    color: Colors.textSecondary,
  },
  infoSection: {
    marginTop: 18,
  },
  infoLabel: {
    fontSize: 12,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 1.1,
    color: Colors.textMuted,
    marginBottom: 8,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.text,
  },
  interestWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  interestChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#D9DDE6',
    backgroundColor: '#FBFCFE',
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  interestChipText: {
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '600',
  },
  likeDetailButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: '#E11D48',
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    ...Shadows.md,
  },
  giftButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: Colors.primary,
    marginTop: 18,
    marginBottom: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    ...Shadows.md,
  },
  giftButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
  coverAccountButton: {
    minHeight: 54,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#BFD7D0',
    backgroundColor: '#F0FDF8',
    marginTop: 4,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  coverAccountButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.primary,
  },
  detailHeroCard: {
    borderRadius: 28,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    overflow: 'hidden',
    backgroundColor: Colors.white,
  },
  detailHeroImageWrap: {
    height: 360,
    backgroundColor: '#E8EBF3',
    position: 'relative',
  },
  detailPhotoCarousel: {
    flex: 1,
  },
  detailHeroImage: {
    height: '100%',
  },
  detailPhotoArrow: {
    position: 'absolute',
    top: '45%',
    zIndex: 4,
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(17, 24, 39, 0.52)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  detailPhotoArrowLeft: {
    left: 12,
  },
  detailPhotoArrowRight: {
    right: 12,
  },
  detailPhotoCount: {
    position: 'absolute',
    right: 14,
    top: 14,
    minHeight: 30,
    borderRadius: 999,
    backgroundColor: 'rgba(17, 24, 39, 0.64)',
    paddingHorizontal: 11,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  detailPhotoCountText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.white,
  },
  detailHeroFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  matchesList: {
    paddingTop: 8,
    paddingBottom: 26,
    gap: 12,
  },
  likesViewWrap: {
    flex: 1,
  },
  likesSegmentedControl: {
    marginTop: 4,
    marginBottom: 8,
    borderRadius: 14,
    backgroundColor: '#FFFFFFB8',
    borderWidth: 1,
    borderColor: '#E7EAF0',
    padding: 3,
    flexDirection: 'row',
    gap: 3,
  },
  likesSegmentButton: {
    flex: 1,
    minHeight: 34,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  likesSegmentButtonActive: {
    backgroundColor: Colors.primary,
  },
  likesSegmentText: {
    fontSize: 11,
    fontWeight: '800',
    color: Colors.primary,
    textAlign: 'center',
  },
  likesSegmentTextActive: {
    color: Colors.white,
  },
  matchCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    backgroundColor: Colors.white,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    ...Shadows.card,
  },
  matchImage: {
    width: 72,
    height: 72,
    borderRadius: 18,
    backgroundColor: '#E8EBF3',
  },
  matchFallback: {
    width: 72,
    height: 72,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  matchFallbackLetter: {
    fontSize: 30,
    fontWeight: '800',
    color: Colors.white,
  },
  matchInfo: {
    flex: 1,
    marginHorizontal: 12,
  },
  matchTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  matchName: {
    flex: 1,
    fontSize: 18,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.4,
  },
  matchMeta: {
    marginTop: 5,
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },
  matchIntent: {
    marginTop: 3,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  matchDate: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  giftModalSubtitle: {
    marginTop: -2,
    marginBottom: 10,
    fontSize: 14,
    color: Colors.textSecondary,
  },
  giftList: {
    paddingBottom: 12,
    gap: 10,
  },
  giftLoadingWrap: {
    minHeight: 220,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 20,
  },
  giftLoadingText: {
    marginTop: 12,
    fontSize: 14,
    lineHeight: 21,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  giftItem: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    backgroundColor: '#FBFCFE',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
  },
  giftItemActive: {
    borderColor: Colors.primary,
    backgroundColor: '#F5F7FF',
  },
  giftIconWrap: {
    width: 56,
    height: 56,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    marginRight: 12,
  },
  giftImage: {
    width: '100%',
    height: '100%',
  },
  giftInfo: {
    flex: 1,
    marginRight: 12,
  },
  giftName: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  giftDescription: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 18,
    color: Colors.textSecondary,
  },
  giftMeta: {
    alignItems: 'flex-end',
    gap: 6,
  },
  giftPaymentSection: {
    marginTop: 12,
    gap: 10,
  },
  giftPaymentModes: {
    gap: 8,
  },
  coverAccountContent: {
    paddingHorizontal: 20,
    paddingBottom: 26,
    gap: 14,
  },
  coverAccountLoading: {
    minHeight: 220,
    alignItems: 'center',
    justifyContent: 'center',
  },
  coverAccountEmpty: {
    minHeight: 240,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
  },
  coverAccountHero: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: '#D1FAE5',
    backgroundColor: '#ECFDF5',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  coverAccountIcon: {
    width: 44,
    height: 44,
    borderRadius: 16,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  coverAccountTitle: {
    fontSize: 15,
    fontWeight: '900',
    color: Colors.text,
  },
  coverAccountSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  coverAccountTotal: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.primary,
  },
  coverAccountItems: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    padding: 12,
    gap: 8,
  },
  coverAccountItemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  coverAccountItemName: {
    flex: 1,
    minWidth: 0,
    fontSize: 13,
    fontWeight: '700',
    color: Colors.text,
  },
  coverAccountItemPrice: {
    fontSize: 13,
    fontWeight: '900',
    color: Colors.primary,
  },
  coverAccountMoreText: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  coverAccountApprovalNote: {
    marginTop: 2,
    color: Colors.textSecondary,
    fontSize: 12,
    lineHeight: 18,
    fontWeight: '700',
    textAlign: 'center',
  },
  approvedCoverContent: {
    paddingBottom: 24,
    gap: 14,
  },
  giftPaymentModeCard: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  giftPaymentModeCardActive: {
    borderColor: Colors.primary,
    backgroundColor: '#F5F7FF',
  },
  giftPaymentModeCardDisabled: {
    opacity: 0.5,
  },
  giftPaymentModeTextWrap: {
    flex: 1,
  },
  giftPaymentModeTitle: {
    color: '#111827',
    fontSize: 13,
    fontWeight: '800',
  },
  giftPaymentModeTitleActive: {
    color: Colors.primary,
  },
  giftPaymentModeDescription: {
    marginTop: 2,
    color: '#64748B',
    fontSize: 11,
    lineHeight: 15,
  },
  giftPaymentModeText: {
    flex: 1,
    color: '#64748B',
    fontSize: 11,
    lineHeight: 15,
  },
  giftPaymentBox: { marginTop: 12, padding: 12, borderRadius: 16, backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0' },
  giftPaymentHeading: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  giftPaymentLabel: { color: '#111827', fontSize: 13, fontWeight: '800' },
  giftPaymentHint: { marginTop: 2, maxWidth: 230, color: '#64748B', fontSize: 10, lineHeight: 14 },
  giftPaymentTotal: { color: Colors.primary, fontSize: 18, fontWeight: '900' },
  giftRewardsSummary: {
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#D1FAE5',
    gap: 7,
  },
  giftRewardsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  giftRewardsLabel: {
    color: '#047857',
    fontSize: 11,
    fontWeight: '700',
  },
  giftRewardsValue: {
    color: '#064E3B',
    fontSize: 11,
    fontWeight: '900',
  },
  giftRewardsToggle: {
    marginTop: 4,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  giftStripeCardWrap: {
    marginTop: 12,
    gap: 8,
  },
  giftStripeBox: {
    marginTop: 4,
    gap: 8,
  },
  giftStripeCardField: {
    width: '100%',
    height: 54,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#D8DDE8',
  },
  giftUnavailableText: {
    fontSize: 12,
    lineHeight: 18,
    color: '#B45309',
    textAlign: 'center',
  },
  giftSendButton: {
    minHeight: 56,
    borderRadius: 18,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    ...Shadows.md,
  },
  giftSendButtonText: {
    color: Colors.white,
    fontSize: 15,
    fontWeight: '900',
  },
  giftStripeNote: {
    fontSize: 12,
    color: Colors.success,
    textAlign: 'center',
  },
  giftStripeWarning: {
    marginTop: 12,
    fontSize: 12,
    lineHeight: 18,
    color: '#B45309',
  },
  giftPrice: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.primary,
  },
  promptOverlay: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    backgroundColor: 'rgba(18, 18, 42, 0.22)',
  },
  promptBackdrop: {
    ...StyleSheet.absoluteFillObject,
  },
  likeGiftPromptCard: {
    borderRadius: 28,
    backgroundColor: Colors.white,
    paddingHorizontal: 22,
    paddingTop: 24,
    paddingBottom: 18,
    alignItems: 'center',
    ...Shadows.lg,
  },
  likeGiftPromptIcon: {
    width: 62,
    height: 62,
    borderRadius: 24,
    backgroundColor: '#E11D48',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  likeGiftPromptTitle: {
    fontSize: 23,
    fontWeight: '900',
    color: Colors.text,
    textAlign: 'center',
    letterSpacing: -0.5,
  },
  likeGiftPromptText: {
    marginTop: 8,
    fontSize: 15,
    lineHeight: 22,
    fontWeight: '600',
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  likeGiftPromptActions: {
    width: '100%',
    flexDirection: 'row',
    gap: 10,
    marginTop: 22,
  },
  likeGiftLaterButton: {
    flex: 1,
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F8FAFC',
    alignItems: 'center',
    justifyContent: 'center',
  },
  likeGiftLaterText: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  likeGiftSendButton: {
    flex: 1.2,
    minHeight: 52,
    borderRadius: 18,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 7,
    ...Shadows.md,
  },
  likeGiftSendText: {
    flexShrink: 1,
    fontSize: 15,
    fontWeight: '900',
    color: Colors.white,
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(18, 18, 42, 0.18)',
  },
  modalBackdrop: {
    flex: 1,
  },
  modalCard: {
    maxHeight: '92%',
    backgroundColor: Colors.white,
    borderTopLeftRadius: 30,
    borderTopRightRadius: 30,
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 18,
  },
  modalHandle: {
    width: 54,
    height: 6,
    borderRadius: 999,
    backgroundColor: '#D9DDE6',
    alignSelf: 'center',
    marginBottom: 8,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: 10,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: Colors.text,
  },
  modalHeaderActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  headerGiftButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F7F0F2',
    borderWidth: 1,
    borderColor: '#F0DCE3',
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalContent: {
    paddingBottom: 12,
  },
  consentContent: {
    paddingBottom: 8,
  },
  consentIconWrap: {
    width: 58,
    height: 58,
    borderRadius: 20,
    backgroundColor: '#EEF2FF',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  consentText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.textSecondary,
    marginBottom: 12,
  },
  privacyNoticeButton: {
    minHeight: 46,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 14,
    marginTop: 2,
    marginBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  privacyNoticeText: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  consentCheckRow: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E1E5ED',
    backgroundColor: '#FBFCFE',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  consentCheckbox: {
    width: 26,
    height: 26,
    borderRadius: 8,
    borderWidth: 1.5,
    borderColor: '#CBD5E1',
    backgroundColor: Colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  consentCheckboxActive: {
    borderColor: Colors.primary,
    backgroundColor: Colors.primary,
  },
  consentCheckText: {
    flex: 1,
    fontSize: 14,
    lineHeight: 20,
    fontWeight: '700',
    color: Colors.text,
  },
  profileHero: {
    alignItems: 'center',
    marginBottom: 18,
  },
  profilePhotoWrap: {
    alignItems: 'center',
  },
  profilePhoto: {
    width: 126,
    height: 126,
    borderRadius: 63,
    overflow: 'hidden',
    borderWidth: 4,
    borderColor: Colors.white,
    backgroundColor: '#E7EAF0',
    ...Shadows.md,
  },
  profilePhotoImage: {
    width: '100%',
    height: '100%',
  },
  profilePhotoFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  profilePhotoLetter: {
    fontSize: 44,
    fontWeight: '800',
    color: Colors.white,
  },
  photoButton: {
    marginTop: 12,
    minHeight: 46,
    borderRadius: 16,
    backgroundColor: Colors.primary,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  photoButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.white,
  },
  profileGalleryBlock: {
    marginBottom: 18,
  },
  galleryHeader: {
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  galleryTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  galleryCount: {
    minWidth: 44,
    minHeight: 30,
    borderRadius: 999,
    backgroundColor: '#F4F6FA',
    textAlign: 'center',
    textAlignVertical: 'center',
    paddingHorizontal: 10,
    paddingTop: Platform.OS === 'ios' ? 6 : 5,
    fontSize: 13,
    fontWeight: '800',
    color: Colors.textSecondary,
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  galleryPhotoSlot: {
    width: '31%',
    aspectRatio: 1,
    borderRadius: 18,
    overflow: 'hidden',
    backgroundColor: '#EEF1F6',
    borderWidth: 1,
    borderColor: '#E1E5ED',
  },
  galleryPhotoImage: {
    width: '100%',
    height: '100%',
  },
  addPhotoSlot: {
    alignItems: 'center',
    justifyContent: 'center',
    borderStyle: 'dashed',
    backgroundColor: '#FAFBFD',
  },
  addPhotoText: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '800',
    color: Colors.primary,
  },
  emptyPhotoSlot: {
    backgroundColor: '#F7F8FB',
    opacity: 0.75,
  },
  primaryBadge: {
    position: 'absolute',
    left: 8,
    top: 8,
    minHeight: 24,
    borderRadius: 999,
    backgroundColor: 'rgba(17, 24, 39, 0.68)',
    paddingHorizontal: 9,
    justifyContent: 'center',
  },
  primaryBadgeText: {
    fontSize: 11,
    fontWeight: '800',
    color: Colors.white,
  },
  makePrimaryButton: {
    position: 'absolute',
    left: 8,
    top: 8,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(17, 24, 39, 0.68)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  deletePhotoButton: {
    position: 'absolute',
    right: 8,
    top: 8,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(190, 18, 60, 0.82)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  fieldBlock: {
    marginBottom: 18,
  },
  fieldLabelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 6,
  },
  fieldLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  fieldInfoButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  helperText: {
    marginBottom: 10,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  choiceButton: {
    minHeight: 56,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: Colors.white,
  },
  choiceValue: {
    flex: 1,
    marginRight: 12,
    fontSize: 16,
    fontWeight: '500',
    color: Colors.text,
  },
  choicePlaceholder: {
    color: '#9CA3AF',
    fontWeight: '400',
  },
  choiceDescription: {
    marginTop: 8,
    fontSize: 12,
    lineHeight: 17,
    color: Colors.textMuted,
  },
  choiceList: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: Colors.white,
  },
  choiceItem: {
    minHeight: 52,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#F2F4F7',
  },
  choiceItemActive: {
    backgroundColor: '#F8FAFC',
  },
  choiceItemLast: {
    borderBottomWidth: 0,
  },
  choiceItemText: {
    fontSize: 15,
    fontWeight: '500',
    color: Colors.text,
  },
  choiceItemTextActive: {
    fontWeight: '700',
    color: Colors.primary,
  },
  filterRow: {
    flexDirection: 'row',
    gap: 12,
  },
  filterHalf: {
    flex: 1,
  },
  filterActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginTop: 10,
  },
  modalActionButton: {
    flex: 1,
    minHeight: 56,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalActionGhostButton: {
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 20,
  },
  modalActionGhostButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  modalActionPrimaryButton: {
    backgroundColor: Colors.primary,
    paddingHorizontal: 20,
    marginTop: 0,
    ...Shadows.md,
  },
  filterGhostButton: {
    minHeight: 56,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterGhostButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  textAreaInput: {
    minHeight: 92,
    paddingTop: 14,
  },
  editorInterestChip: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#D7DCE6',
    backgroundColor: '#FBFCFE',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  editorInterestChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  editorInterestText: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  editorInterestTextActive: {
    color: Colors.white,
  },
  saveButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 10,
    ...Shadows.md,
  },
  saveButtonDisabled: {
    opacity: 0.65,
  },
  saveButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
});
