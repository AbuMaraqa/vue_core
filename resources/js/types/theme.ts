export type Theme = {
    font: string;
    fontVariable: string;
    primaryColor: string;
    /** Simple mode hides the advanced menus for people who do not know accounting. */
    simpleMode: boolean;
    logoUrl: string | null;
};

export type FontOption = {
    value: string;
    label: string;
    variable: string;
};
