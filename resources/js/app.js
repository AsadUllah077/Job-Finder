import './bootstrap';
import Alpine from 'alpinejs';
import {
    createIcons, ScanLine, LayoutDashboard, Search, Bookmark, BookmarkCheck,
    SlidersHorizontal, RadioTower, LogIn, LogOut, UserPlus, Sprout, Menu,
    ArrowUpRight, ArrowRight, Bell, BellPlus, BriefcaseBusiness, Sparkles,
    CalendarDays, Send, Activity, Compass, Download, Pencil, Trash2, Check,
    History, RefreshCw, Archive, Layers, Settings2, CircleAlert,
} from 'lucide';
window.Alpine = Alpine;
Alpine.start();
createIcons({ icons: {
    ScanLine, LayoutDashboard, Search, Bookmark, BookmarkCheck, SlidersHorizontal,
    RadioTower, LogIn, LogOut, UserPlus, Sprout, Menu, ArrowUpRight, ArrowRight,
    Bell, BellPlus, BriefcaseBusiness, Sparkles, CalendarDays, Send, Activity,
    Compass, Download, Pencil, Trash2, Check, History, RefreshCw, Archive, Layers,
    Settings2, CircleAlert,
}, attrs: { class: 'icon', 'stroke-width': 1.7 } });
