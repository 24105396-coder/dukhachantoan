<script setup>
import { computed } from 'vue';
import {
    Activity, Ambulance, ArrowLeft, ArrowRight, Bell, BellRing, BookOpen, Building2, Calendar, CalendarClock, Car, ChartColumn,
    Check, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, CircleAlert, CircleCheck, CircleHelp, Clock, Cloud, CloudDrizzle,
    CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, CloudSunRain, Database, Download, Droplets, Earth, Ellipsis,
    ExternalLink, Eye, FileText, Flame, FlaskConical, Funnel, Gauge, Globe, Hand, HardDrive, HeartPulse, Hospital, House, Inbox,
    Info, KeyRound, Languages, Layers, LayoutDashboard, LifeBuoy, ListChecks, Lock, LogIn, LogOut, Luggage, Mail, Map, MapPin,
    MapPinned, Megaphone, Menu, MessageSquare, Moon, Mountain, Navigation, Pause, Pencil, Phone, Play, Plus, Radio, RefreshCw,
    Route, ScrollText, Search, Send, Settings, Shield, ShieldAlert, ShieldCheck, Siren, Smartphone, Snowflake, Sparkles, Sun,
    Sunrise, Thermometer, Timer, Tornado, Trash2, TriangleAlert, Umbrella, Upload, User, UserCog, UserPlus, Users, Waves, Wind, X,
} from 'lucide-vue-next';

/**
 * Icon lucide (nét 1.75) tra theo tên kebab-case. Danh sách tường minh để tree-shaking giữ bundle nhỏ.
 * Tên lạ (vd hazard.icon từ dữ liệu) rơi về `fallback`. Icon trang trí luôn aria-hidden; nếu cần nghĩa
 * riêng thì truyền `label` để đọc bằng trình đọc màn hình.
 */
const ICONS = {
    activity: Activity, ambulance: Ambulance, 'arrow-left': ArrowLeft, 'arrow-right': ArrowRight, bell: Bell, 'bell-ring': BellRing,
    book: BookOpen, 'book-open': BookOpen, building: Building2, calendar: Calendar, 'calendar-clock': CalendarClock, car: Car,
    chart: ChartColumn, check: Check, 'chevron-down': ChevronDown, 'chevron-left': ChevronLeft, 'chevron-right': ChevronRight,
    'chevron-up': ChevronUp, 'circle-alert': CircleAlert, 'circle-check': CircleCheck, help: CircleHelp, clock: Clock, cloud: Cloud,
    'cloud-drizzle': CloudDrizzle, 'cloud-fog': CloudFog, fog: CloudFog, 'cloud-lightning': CloudLightning, storm: CloudLightning,
    thunderstorm: CloudLightning, 'cloud-rain': CloudRain, rain: CloudRain, 'heavy-rain': CloudRain, 'cloud-snow': CloudSnow,
    'cloud-sun': CloudSun, 'partly-cloudy': CloudSun, 'cloud-sun-rain': CloudSunRain, database: Database, download: Download,
    droplets: Droplets, flood: Droplets, earth: Earth, earthquake: Activity, ellipsis: Ellipsis, external: ExternalLink, eye: Eye,
    file: FileText, flame: Flame, fire: Flame, wildfire: Flame, flask: FlaskConical, filter: Funnel, gauge: Gauge, globe: Globe,
    hand: Hand, 'hard-drive': HardDrive, 'heart-pulse': HeartPulse, health: HeartPulse, hospital: Hospital, home: House, inbox: Inbox,
    info: Info, key: KeyRound, languages: Languages, layers: Layers, dashboard: LayoutDashboard, 'life-buoy': LifeBuoy,
    rescue: LifeBuoy, 'list-checks': ListChecks, lock: Lock, login: LogIn, logout: LogOut, luggage: Luggage, mail: Mail, map: Map,
    pin: MapPin, 'map-pin': MapPin, 'map-pinned': MapPinned, megaphone: Megaphone, menu: Menu, message: MessageSquare, moon: Moon,
    mountain: Mountain, landslide: Mountain, navigation: Navigation, pause: Pause, edit: Pencil, phone: Phone, play: Play, plus: Plus,
    radio: Radio, refresh: RefreshCw, route: Route, scroll: ScrollText, search: Search, send: Send, settings: Settings,
    shield: Shield, 'shield-alert': ShieldAlert, 'shield-check': ShieldCheck, siren: Siren, smartphone: Smartphone, sms: Smartphone,
    snowflake: Snowflake, cold: Snowflake, sparkles: Sparkles, sun: Sun, clear: Sun, heat: Thermometer, sunrise: Sunrise,
    thermometer: Thermometer, timer: Timer, tornado: Tornado, trash: Trash2, 'triangle-alert': TriangleAlert, alert: TriangleAlert,
    umbrella: Umbrella, upload: Upload, user: User, 'user-cog': UserCog, 'user-plus': UserPlus, users: Users, waves: Waves,
    tsunami: Waves, 'high-tide': Waves, wind: Wind, typhoon: Tornado, close: X, x: X,
};

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 1.75 },
    label: { type: String, default: '' },
    fallback: { type: String, default: 'circle-alert' },
});

const component = computed(() => ICONS[props.name] || ICONS[props.fallback] || CircleAlert);
</script>

<template>
    <component
        :is="component"
        :size="size"
        :stroke-width="strokeWidth"
        class="shrink-0"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label || undefined"
        :role="label ? 'img' : undefined"
        focusable="false"
    />
</template>
