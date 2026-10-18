<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import Bag from '~icons/solar/bag-4-linear';
import Banknote from '~icons/solar/banknote-linear';
import BillList from '~icons/solar/bill-list-linear';
import Book from '~icons/solar/book-linear';
import CalendarIcon from '~icons/solar/calendar-linear';
import BoxMinimalistic from '~icons/solar/box-minimalistic-linear';
import CardReceive from '~icons/solar/card-recive-linear';
import CardSend from '~icons/solar/card-send-linear';
import CartPlus from '~icons/solar/cart-plus-linear';
import CashOut from '~icons/solar/cash-out-linear';
import DocumentText from '~icons/solar/document-text-linear';
import GraphUp from '~icons/solar/graph-up-linear';
import HandMoney from '~icons/solar/hand-money-linear';
import Buildings from '~icons/solar/buildings-2-linear';
import Cart from '~icons/solar/cart-large-2-linear';
import Chart from '~icons/solar/chart-2-linear';
import Danger from '~icons/solar/danger-triangle-linear';
import Box from '~icons/solar/box-linear';
import History from '~icons/solar/history-linear';
import Images from '~icons/solar/gallery-linear';
import Printer from '~icons/solar/printer-linear';
import Circle from '~icons/solar/record-linear';
import Database from '~icons/solar/database-linear';
import Toolbox from '~icons/solar/toolbox-linear';
import Settings from '~icons/solar/settings-linear';
import SettingsMinimalistic from '~icons/solar/settings-minimalistic-linear';
import ShieldUser from '~icons/solar/shield-user-linear';
import UserId from '~icons/solar/user-id-linear';
import UserPlus from '~icons/solar/user-plus-linear';
import Users from '~icons/solar/users-group-rounded-linear';
import UsersTwo from '~icons/solar/users-group-two-rounded-linear';
import Wallet from '~icons/solar/wallet-money-linear';
import LayoutDashboard from '~icons/solar/widget-linear';

/**
 * Icons referenced by name from the server, e.g. `SidebarItem::make('Users')->icon('users')`.
 *
 * They come from the Solar icon set (https://icon-sets.iconify.design/solar/).
 * When a module needs a new icon, import it above and add its name here.
 * Unknown names fall back to a small circle.
 */
const icons: Record<string, Component> = {
    bag: Bag,
    banknote: Banknote,
    'bill-list': BillList,
    book: Book,
    box: Box,
    'box-minimalistic': BoxMinimalistic,
    buildings: Buildings,
    calendar: CalendarIcon,
    'card-receive': CardReceive,
    'card-send': CardSend,
    cart: Cart,
    'cart-plus': CartPlus,
    'cash-out': CashOut,
    chart: Chart,
    contacts: UsersTwo,
    danger: Danger,
    database: Database,
    'document-text': DocumentText,
    'graph-up': GraphUp,
    'hand-money': HandMoney,
    history: History,
    images: Images,
    'layout-dashboard': LayoutDashboard,
    printer: Printer,
    production: Toolbox,
    settings: Settings,
    'settings-minimalistic': SettingsMinimalistic,
    'shield-user': ShieldUser,
    'user-id': UserId,
    'user-plus': UserPlus,
    users: Users,
    wallet: Wallet,
};

const props = defineProps<{
    name: string | null;
}>();

const icon = computed(() => (props.name && icons[props.name]) || Circle);
</script>

<template>
    <component :is="icon" />
</template>
