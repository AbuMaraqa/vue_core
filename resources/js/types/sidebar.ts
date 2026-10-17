export type SidebarLink = {
    type: 'item';
    key: string;
    title: string;
    icon: string | null;
    badge: string | null;
    url: string | null;
    active: boolean;
};

export type SidebarGroup = {
    type: 'group';
    key: string;
    title: string;
    icon: string | null;
    badge: string | null;
    active: boolean;
    children: SidebarLink[];
};

export type SidebarSection = {
    type: 'section';
    key: string;
    title: string;
    items: Array<SidebarLink | SidebarGroup>;
};
