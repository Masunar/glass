# Salvon Components — Authoring Guide

A complete reference for writing components in `salvon/components`. Read this **before** adding or modifying a component so your work matches the existing set exactly. Every snippet below is a real pattern taken from this directory (file paths are cited so you can open the source).

> **Ignore `legacy/`.** It does not reflect current style. The one live exception: `table/index.ts` re-exports `legacy/table`, so the canonical table API is still the legacy `DataTable`/`ApiTable`. Don't copy other legacy idioms.

---

## Table of contents
1. [File & folder structure](#1-file--folder-structure)
2. [Component declaration](#2-component-declaration)
3. [TypeScript & prop types](#3-typescript--prop-types)
4. [Imports & path aliases](#4-imports--path-aliases)
5. [Styling — theme, `sx`, `styled()`](#5-styling)
6. [Customization API — `slotProps`, config, controlled state](#6-customization-api)
7. [Composition — overlays, anchors, callable children](#7-composition)
8. [Accessibility & component states](#8-accessibility--component-states)
9. [Shared utilities & primitives you must know](#9-shared-utilities--primitives)
10. [New-component checklist](#10-new-component-checklist)

---

## 1. File & folder structure

One kebab-case folder per component. Standard layout:

```
select/
  index.ts                 # barrel — the ONLY public surface
  Select.tsx               # PascalCase, ONE component per file, filename = component name
  types/Select.d.ts        # types (or a folder-level types.d.ts)
  useSelectSomething.ts    # co-located hooks, camelCase
  variant/                 # sub-components grouped by role: variant/ | preset/ | control/ | entry-type/
```

Rules:

- **`index.ts` is the only public surface.** It re-exports components as **named** exports and puts types in a **separate `export type` block**. Consumers import from the folder root — `@salvon/components/select` — never a default straight from a `.tsx`.
- **`PascalCase.tsx`, one component per file**, filename equals the component name (`BaseButton.tsx`, `CategoryTree.tsx`).
- **Types** go in `types.d.ts` (folder-level) or `types/Name.d.ts`. These `.d.ts` files hold **real exported types**, not ambient declarations. Trivial components may inline the prop type in the `.tsx`.
  - The `.d` extension sometimes appears in the import specifier (`from './types/Select.d'`). This is intentional in some folders — match the siblings you're editing.
- **Hooks** are co-located as `useXxx.ts` (camelCase), e.g. `category-tree/useDragHover.ts`, `category-tree/useTreeWidth.ts`.
- **Sub-components** live in role folders: `button/preset/`, `switch/variant/`, `form/control/`, `sidebar/variant/`, `omni-search/components/`.

### Barrel styles (both accepted)

Explicit import-then-export (most folders):
```ts
// button/index.ts
import BaseButton from './BaseButton';
import Button from './Button';

export { BaseButton, Button };
export type { BaseButtonProps, ButtonProps } from './types';
```

Inline re-export (category-tree):
```ts
// category-tree/index.ts
export { default as CategoryTree } from './CategoryTree';
export type { CategoryTreeProps, CategoryNode } from './types';
```

---

## 2. Component declaration

### Always a default function declaration
```ts
// select/Select.tsx
export default function Select<T extends string | number>({ options, value, onChange /* … */ }: SelectProps<T>) {
  // …
}
```
Function **declarations**, not arrow consts. Every component `.tsx` `export default`s exactly one.

### No `forwardRef`, no `displayName` — use the React 19 `ref`-as-prop pattern
Refs are a normal prop typed as `ForwardedRef<…>`, consumed with `useImperativeHandle`:
```ts
// tabs/Tabs.tsx
export type TabsRef = ForwardedRef<{ activeTab: string }>;
export type TabsProps = {
  items: TabsItems;
  ref?: TabsRef;
  slotProps?: { container?: SlotItem<DivProps>; tabs?: SlotItem<MUITabsProps> };
};

export default function Tabs({ items, ref }: TabsProps) {
  const [activeTab, setActiveTab] = useState(items[0]?.name);
  useImperativeHandle(ref, () => ({ activeTab }));
  // …
}
```
Overlays expose imperative handles the same way — Modal: `useImperativeHandle(ref, () => ({ openModal, closeModal }))`; Popover: `{ openPopover, closePopover }`.

### `styled()` components are `const` + default export
```ts
// slider/StyledSlider.tsx
const StyledSlider = styled(Slider)(({ theme }) => ({ /* … */ }));
export default StyledSlider;
```

### Variant / preset dispatch via `createElement`
When a prop selects between sibling implementations, `switch` and return `createElement`:
```ts
// switch/Switch.tsx
export default function Switch({ variant = 'plain', ...props }: SwitchProps) {
  switch (variant) {
    case 'mono-color': return createElement(MonoColorSwitch, props);
    case 'indicated':  return createElement(IndicatedSwitch, props);
    case 'mui':        return createElement(PlainSwitch, props);
    default:           return createElement(PlainSwitch, props);
  }
}
```
Same idiom in `button/Button.tsx` (`preset` → `AddButton`/`DeleteButton`/…) and `form/control/FormControl.tsx` (`variant` → `FormInputText`/`FormSelect`/…).

### Private sub-components are plain in-file functions
```ts
// tabs/Tabs.tsx — not exported, declared below the default
function TabPanel({ value, activeTab, children }: TabPanelProps) {
  if (value !== activeTab) return null;
  return <Div>{children}</Div>;
}
```

---

## 3. TypeScript & prop types

### Naming
- Props: `export type XxxProps` — **type aliases, not `interface`**.
- Refs: `export type XxxRef`.

### Extend MUI by intersection + `Omit`, with `MUI`-prefixed imports
When wrapping a same-named MUI component, alias its import and prop type with a `MUI` prefix, then intersect:
```ts
// button/types.ts
import { type ButtonProps as MUIBaseButtonProps } from '@mui/material';

export type BaseButtonProps = Omit<MUIBaseButtonProps, 'href'> & {
  path?: GeneratePathUrl;
  pathParams?: GeneratePathParams;
  icon?: ReactNode;
  confirm?: string;
};
```
Other real shapes:
```ts
export type IconButtonProps      = { square?: boolean } & MUIIconButtonProps;
export type PositiveNegativeProps = { condition: boolean } & ChipProps;
export type KbdProps             = ChipProps;                 // pure alias is fine
export type FormInputTextProps   = Omit<TextFieldProps, 'name' | 'value' | 'onChange' | 'onInput'>
                                 & { name: string } & LabelMode;
```

### Generics for value-bearing components
Constrain to `string | number`:
```ts
export type SelectProps<T extends string | number> = { options: SelectOption<T>[] } & ValueProps<T>;
export default function Select<T extends string | number>(props: SelectProps<T>) { /* … */ }
```

### Discriminated union on `multiple` switches value + onChange arity
```ts
// select/types/Select.d.ts
type ValueProps<T> =
  | ({ multiple: true;  value?: T[] | null } & OnChangeArrayableHandler<T>)
  | ({ multiple?: false | undefined; value?: T | null } & OnChangeHandler<T>);
```

### `never`-based mutual exclusion for conflicting prop combos
```ts
// category-tree/types.d.ts
type AutoHeightProps  = { autoHeight: true;  autoHeightOffset?: number; height?: never };
type FixedHeightProps = { autoHeight?: false; autoHeightOffset?: never; height?: CSSProperties['height'] };
export type CategoryTreeProps = BaseCategoryTreeProps & (AutoHeightProps | FixedHeightProps);
```

### Misc
- Use `import type` / inline `type` for type-only imports (`import { Chip, type ChipProps } from '@mui/material'`).
- Pragmatic `any` / `@ts-ignore` is **only** tolerated at MUI integration seams (Autocomplete internals, `SxProps`→style-object casts, palette). Don't let it leak into your own logic.

---

## 4. Imports & path aliases

Grouped, blank-line-separated, in this order:

```ts
// 1. MUI / third-party UI
import { Autocomplete, TextField, CircularProgress, type AutocompleteProps } from '@mui/material';
import { Tree } from 'react-arborist';

// 2. Local relative type imports
import type { SelectProps, SelectOption } from './types/Select.d';

// 3. react + libs
import { useState, useMemo, type ReactNode } from 'react';
import { Controller } from 'react-hook-form';

// 4. @salvon/* LAST — sub-grouped components / hooks / types / utils
import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';
import { voc } from '@salvon/utils/object';
```

- Alias: **`@salvon/*` → `salvon/*`**.
- **Cross-component imports always go through the barrel**: `@salvon/components/div`, `@salvon/components/popover/ConfirmPopover` — never a deep path into another component's `.tsx`.

---

## 5. Styling

### Core fact: components are theme-agnostic
Concrete token values live in the **app** (`app/src/config/theme.ts` → `lightTheme` / `darkTheme` / `breakpoints`) and are injected into MUI via `salvon/provider/ThemeProvider.tsx`. Components read them back at runtime — they never define palette values themselves.

### Accessing the theme
```ts
// hooks/useTheme.ts exposes these:
const theme    = useTheme();       // full Theme
const palette  = usePalette();     // theme.palette ?? {}   ← dominant in JSX
const mode     = useThemeMode();   // 'light' | 'dark'
const isDark   = useIsDarkMode();
```
In JSX components use `usePalette()`. In `styled()` blocks use the injected `theme` callback arg instead.

### The `palette.salvon.*` namespace — custom design tokens
`Theme` (`salvon/types.ts`) extends MUI's palette with a `salvon` object of **per-component tokens** in two flavors:

- **Whole `SxProps` blobs**: `salvon.card`, `salvon.control_card`, `salvon.topbar`, `salvon.menu.*`, `salvon.table.*`, `salvon.popover.paper`.
- **Flat color-string maps**: `salvon.omni_search` (`frame/border/chipBg/…`), `salvon.file_upload.color.*`, `salvon.price_settlement.*`.

Consume the token that exists rather than hardcoding it:
```tsx
// card/Card.tsx
const palette = usePalette();
<Paper sx={{
  ...(palette?.salvon?.card ?? {}),                 // theme token first
  ...voc(!!padding && !heading, { padding }),
  ...voc(!!heading, { overflow: 'hidden' }),
  ...sx,                                            // caller sx wins
  ...voc(!!blank, { backgroundColor: 'transparent' }),
}} />
```

### `sx` is the primary styling API. Canonical merge order: **base → theme token → caller `sx` last.**
```tsx
// button/BaseButton.tsx — defaults BEFORE {...props} so the caller can override them
<MUIButton
  variant="contained"
  disableElevation
  {...props}
  sx={{ textTransform: 'none', borderRadius: '8px', ...sx }}   // ...sx last
  startIcon={icon}
/>
```
The rule has two halves:
1. Spreadable **prop defaults** (`variant`, `size`, …) go **before** `{...props}`.
2. **`sx` fragments** end with `...sx` (or `...slotProps?.x?.sx`) so caller styles win.

### `voc` — conditional style spreading (use instead of inline `&&`)
`voc(condition, value, falseValue = {})` from `@salvon/utils/object` returns `value` when the condition holds, else `falseValue`:
```tsx
sx={{
  ...voc(!!fw, { width: '100%' }),
  ...voc(!!error, { borderColor: 'error.main' }),
}}
```

### `styled()` — rare (~8 usages). Reserve it for pseudo-elements, deep MUI slots, or per-render prop-driven CSS.
Theme-callback form targeting MUI slot classes, with the `mode === 'dark'` ternary and the hex-alpha suffix idiom:
```ts
// slider/StyledSlider.tsx
const StyledSlider = styled(Slider)(({ theme }) => ({
  height: 6,
  '& .MuiSlider-rail': {
    backgroundColor: theme.palette.mode === 'dark' ? '#303030' : '#e6eaf0',
    borderRadius: 999,
  },
  '& .MuiSlider-thumb': {
    height: 18, width: 18,
    border: `2px solid ${theme.palette.primary.main}`,
    '&:hover, &.Mui-focusVisible': {
      boxShadow: `0 0 0 6px ${theme.palette.primary.main}24`,   // '24' ≈ 14% alpha
    },
    transition: 'box-shadow 0.15s ease',
  },
}));
```

Prop-driven `styled` uses **`shouldForwardProp`** (the one canonical example) to keep the custom prop off the DOM:
```ts
// icon-button/BaseIconButton.tsx
const StyledIconButton = styled(MUIIconButton, {
  shouldForwardProp: (prop) => prop !== 'square',
})<{ square?: boolean }>(({ theme, square }) => {
  if (!square) return {};
  return {
    width: 36, height: 36, borderRadius: '9px',
    border: `1px solid ${theme.palette.divider}`,
    color: theme.palette.text.secondary,
    '&:hover': { background: theme.palette.action.hover, color: theme.palette.text.primary },
  };
});
```

`styled` can also wrap another local component (layered switch variants):
```ts
// switch/variant/*  — PlainSwitch (base) ← MonoColorSwitch / IndicatedSwitch
const MonoColorSwitch = styled((props: SwitchProps) => <PlainSwitch {...props} />)(({ theme }) => ({ /* … */ }));
```

More commonly prop→style is imperative in the function body:
```ts
// div/Flex.tsx
const justifyContent = jBetween ? 'space-between' : jCenter ? 'center' : jEnd ? 'flex-end' : undefined;
```

### Dark mode — two idioms, both accepted (match the file you're in)
```ts
theme.palette.mode === 'dark' ? '#181818' : '#ffffff'      // ternary
theme.applyStyles('dark', { backgroundColor: '#181818' })   // MUI helper (see MonoColorSwitch)
```

### Animations — global CSS utility classes, no `keyframes`
`salvon/styles.css` defines `.salvon-animate`, `.salvon-animate-all`, `.salvon-animate-height` (250ms) plus opt-outs `.disable-salvon-animate`, `.disable-salvon-animate-all(-soft)`. Toggle via `className`:
```tsx
<ToggleButtonGroup className="disable-salvon-animate-all" />     // opt out of global anim
<Div className="salvon-animate-height" />                        // animate height
```
Inline `0.15s ease` transitions inside `styled` blocks are fine. Use `theme.transitions.create([...])` for MUI-timed transitions.

### Tokens / misc
- No dedicated constants file. Border-radius (`8`, `9px`, `999`), font sizes (`0.875rem`, `12px`), and shadow strings are often hardcoded per component.
- Prefer **semantic theme strings** where they exist: `'divider'`, `'primary.main'`, `'action.hover'`, `'text.secondary'`, `'text.primary'`.
- Font is **Inter** (Google Fonts import in `styles.css`).
- Use `Div`/`Flex`, not raw `Box` (see §9).

---

## 6. Customization API

### `slotProps` is the universal customization surface (not a blanket top-level `sx`/`className`)
Key it by **named internal slots**, each `SlotItem<T>` (`= Partial<T>`, from `salvon/types.ts`):
```ts
// control-card/ControlCard.tsx
slotProps?: {
  root?: SlotItem<FlexProps>;
  control?: SlotItem<RadioProps & CheckboxProps>;
  content?: SlotItem<FlexProps>;
  label?: SlotItem<TypographyProps>;
  description?: SlotItem<TypographyProps>;
};
```
Spread the slot onto its element, then merge its `sx` last:
```tsx
<Control
  checked={checked}
  disabled={disabled}
  disableRipple
  {...slotProps?.control}
  sx={{ p: 0, mt: 0, ...slotProps?.control?.sx }}
/>

<Typography variant="body2" {...slotProps?.label}
  sx={{ fontWeight: 600, color: 'text.primary', ...slotProps?.label?.sx }}>
  {label}
</Typography>
```
`style?: CSSProperties` is passed through only where a **raw DOM element** is rendered:
```tsx
// form/Form.tsx
<form noValidate style={{ width: '100%', ...(style ?? {}) }} />
```

### Config-array data, not compound children
Prefer an `options` / `items` / `data` array that the parent renders internally, over `<Parent><Child/></Parent>`. Real config shapes:
```ts
type SelectOption<T>  = { value: T; label: string | number; disabled?: boolean };
type TabItem          = { name: string; label: ReactNode; content: ReactElement; labelProps?: object };
type CategoryNode     = { id: string; name: string; is_active?: boolean; children?: CategoryNode[] };
type OmniSearchItem   = { key: string; search_name: string; icon?: ReactNode; label?: string;
                          shortcut?: string; onSelect?: () => void; render?: FC /* escape hatch */ };
```
Convention: identity key (`value`/`id`/`name`/`key`), `label` for display, `disabled?` per item, plus an optional `render`/`actions` **escape hatch** that always sits alongside a built-in default so it stays optional:
```ts
actions?: (node: CategoryNode) => ReactNode;               // category-tree
renderOption?: (option: Option<T>, handleChange: () => void) => ReactElement;  // groups
```

### Render props — naming & how helpers are passed
Render props are **`render` / `renderXxx`** (camelCase, verb-first): `render`, `renderOption`, `renderValue`, `renderCard`, `renderColumnHeader`, `renderDetail`. Callbacks that emit UI without the `render` verb use `actions` / `footer`.

How you pass data into that callback depends on what it is — **do not invent a named `XxxApi` / `XxxRenderer` / `XxxContext` bag type** (the lone `...Api` bag in the tree is legacy and should not be copied). Pick one of two shapes:

1. **Positional args for domain values** — ordered, self-evident, no wrapper type. This is the default for the primary item plus a small fixed set of related values:
   ```ts
   renderColumnHeader?: (column: KanbanColumn, count: number) => ReactNode;   // kanban
   renderDetail?: (item: T, close: () => void) => ReactNode;                  // kanban
   renderOption?: (option: Option<T>, handleChange: (v: T) => void) => ReactElement;  // groups
   actions?: (node: CategoryNode) => ReactNode;                               // category-tree
   ```

2. **Inline `{ }` object literal for a bag of named helpers** — when you're handing over a *set of helpers* rather than ordered domain data, destructure an inline literal (no named type). Matches Popover / Modal / form:
   ```ts
   children: CallableNode<{ closePopover: () => void }>;          // popover — inline literal
   footer?: CallableNode<ModalElementCallbackProps>;             // modal — the ONE named exception, see below
   afterInput?: ({ error }: { error?: string }) => ReactNode;    // form control
   ```
   The primary domain value still stays positional; only the helper bag is the literal:
   ```ts
   // kanban — item positional, drag helpers as an inline bag
   renderCard: (
     item: T,
     { dragHandleRef, isDragging }: { dragHandleRef: DragHandleRef; isDragging: boolean },
   ) => ReactNode;
   ```

Rules for the bag:
- **camelCase, verb-noun** field names (`closeModal`, `closePopover`, `dragHandleRef`, `isDragging`, `reloadRow`). snake_case appears **only** on domain data models (`is_active`, `search_name`), never on helper fields.
- Reuse `CallableNode<T>` / `CallableChildren<T>` from `@salvon/types` for callable children; `Noop` for a no-arg helper.
- Name a bag type (`XxxCallbackProps`) **only** when the same bag is reused across several props of one component — the modal family does this (`ModalElementCallbackProps`, `ConfirmActionCallbackProps`). For a bag used by a single render prop, keep it inline.
- A named signature type is fine when it's a *reusable function type*, not a helper bag — e.g. `DragHandleRef = (el: Element | null) => void`.

### Optional-controlled state (the standard value pattern)
```ts
// toggle-group/ToggleGroup.tsx
const [internalValue, setInternalValue] = useState<T[]>(defaultValue ?? []);
const val = value ?? internalValue;                        // controlled wins

function handleChange(next: T[]) {
  onChange?.(next);
  if (!value) setInternalValue(next);                      // self-update ONLY when uncontrolled
}
```
Seed uncontrolled usage with `defaultValue`. For form (RHF) controls make the value controlled-safe: `value={field.value ?? ''}`.

### Overlays use `ControllableOptionalState`
```ts
// salvon/types.ts
export type ControllableOptionalState = { open?: boolean; setOpen?: SetState<boolean> };
```
```ts
// popover/Popover.tsx
const openState    = open ?? internalControlledOpen;
const setOpenState = setOpen ?? setInternalControlledOpen;
```
Also expose an imperative `ref` (`{ openModal, closeModal }`, `{ openPopover, closePopover }`).

### `onChange` conventions
Pass **enriched context**, not raw events:
```ts
// select
onChange?: (value: T | null, option: SelectOption<T> | null,
            ctx: { event: SyntheticEvent; reason: string; details?: unknown }) => void;
```
Group / form `onChange` may **return** a replacement value:
```ts
// checkbox-group/CheckboxGroup.tsx
onChange?: (value: T[], currentValue: T[]) => T[] | void;
// …
setChecked(onChange?.(next, current) ?? next);
```
RHF controls layer three tiers (`form/control/types.d.ts`):
```ts
onChange?:        (value, event) => T | void;              // transform; may return replacement
onChangeOverride?: (event, fieldOnChange) => void;         // full takeover of field.onChange
onBlur?, onBlurOverride?                                    // same pair for blur
```

### Defaults set in destructuring (never `defaultProps`); may derive from theme / other props
```ts
// select/Select.tsx
function Select<T>({ clearable = true, closeOnSelect = !multiple, fullWidth = true }: SelectProps<T>) {}
// form/control/FormInputText.tsx
const resolvedLabelMode = labelMode ?? palette.salvon?.form?.labelMode ?? 'material';
```
Booleans default `false` and read as positive flags (`disabled`, `loading`, `multiple`, `keepMounted`).

### Variants via string enum + `createElement` — no polymorphic `as`
There is **no** MUI-style `component`/`as` element prop. Instead a `variant`/`preset`/`component` **string** dispatches to a sibling component (see §2). `component` when present is an enum, not an element:
```ts
// checkbox-group
component?: 'radio' | 'checkbox' | 'switch' | 'radio-card' | 'checkbox-card';
```

---

## 7. Composition

### Anchors via `cloneElement`
Overlays and confirm-wrapped buttons take `anchor?: JSX.Element` and inject `onClick`/`ref`:
```tsx
// modal/Modal.tsx
{anchor && cloneElement(anchor, { onClick: openModal })}
// popover/Popover.tsx
cloneElement(anchor, { ref: anchorRef, onClick: openPopover })
// button/BaseButton.tsx — button wraps itself
button = <ConfirmPopover anchor={button} message={confirm} />;
```

### Callable children exposing a close handler
```ts
// salvon/types.ts
export type CallableNode<T>     = ReactNode | ((props: T) => ReactNode);
export type CallableChildren<T> = { children: CallableNode<T> };
```
```tsx
// modal/Modal.tsx
let content = children;
if (isFunction(content)) content = content({ closeModal });
```

### Shared state via react-hook-form context (not bespoke React context)
```tsx
// form/Form.tsx
<FormProvider {...methods}><form noValidate>{children}</form></FormProvider>
// form/control/FormInputText.tsx
const { control } = useCurrentForm();
<Controller name={name} control={control} defaultValue={defaultValue} render={({ field, fieldState }) => (/* … */)} />
```
Do not invent a `TabsContext`/`SelectContext`; keep parent-child state inside the single parent component.

### Modal family = composition of wrappers
`FormModal` = `Modal` + `Form` + `Button` presets, config passed down via `slotProps.modal` / `slotProps.footerWrapper`. `ConfirmModal` / `ActionModal` extend `Modal` through `ExtendableModalProps → CancelableModalProps → ConfirmableModalProps`.

---

## 8. Accessibility & component states

### A11y is delegated to MUI
Autocomplete, Dialog, Tabs, ToggleButtonGroup, TextField, Checkbox/Radio provide roles, aria, focus, and keyboard handling for free — lean on them. Hand-roll only when there's no MUI base:
```ts
// omni-search — ⌘K global open + arrow nav + Enter select + scroll-into-view
useEffect(() => { /* keydown listener for (e.metaKey && e.key === 'k') */ }, []);
// onKeyDown drives an `active` index; rowRef scrolls the active row into view
```
Prefer a declarative `onSelect` over an onClick embedded in a `render` escape hatch, specifically so keyboard selection keeps working.

### Anti-pattern to avoid
`ControlCard` uses `<Flex onClick>` (a `div`) with **no** `role="button"`/`tabIndex`/key handlers — it relies on its inner MUI Radio/Checkbox for semantics. If you build a clickable surface **without** an inner focusable control, add `role`, `tabIndex={0}`, and `onKeyDown` (Enter/Space) yourself.

### States — provide and route through MUI
- **`loading?: boolean`** is near-universal. Render varies: Select → `CircularProgress` adornment + `loadingText`; Button → MUI `loading` (hides icon); DataTable → `loader` enum (`'none'|'spinner'|'linear'|'skeleton'|'custom'`); CategoryTree → `Skeleton` rows until measured. Overlays **block close while loading**.
- **`error`** (`string` for groups, `boolean` for Select) + **`helperText`** → MUI `FormHelperText` / `TextField error`. RHF controls derive error from `fieldState`.
- **`disabled?: boolean`** throughout; combine for RHF as `disabled || field.disabled`; per-item via `option.disabled`.
- **Empty**: `noOptionsText={t('no_results')}`, `noResultsItem` (OmniSearch), a `CenterMessage` for tables. Use `t()` from `@salvon/hooks/useTranslation` for all empty/loading copy.

---

## 9. Shared utilities & primitives

Know and reuse these — don't reinvent them:

| Import | What it is |
|---|---|
| `@salvon/components/div` → `Div`, `Flex` | Layout primitives. **Use instead of `Box`.** `Div` extends `BoxProps` with spacing shorthands (`m/mt/mb/mr/ml/p/pt/…`, `fw`, `fh`); `Flex` adds boolean alignment (`jCenter`, `jBetween`, `jEnd`, `aCenter`, `center`, `column`, `wrap`, `gap`). Both build `sx` imperatively with `voc`. |
| `@salvon/types` → `SlotItem<T>` | `= Partial<T>`. The `slotProps` building block. Also `Noop`, `CallableNode`, `CallableChildren`, `ControllableOptionalState`, `AnchorElement`. |
| `@salvon/utils/object` → `voc` | `valuesOnCondition(cond, value, falseValue={})` — conditional style spreading. |
| `@salvon/utils/type-check` → `isArray`, `isString`, `isFunction` | Type guards — use these, not raw `Array.isArray`. |
| `@salvon/utils/generate-path` → `GeneratePathUrl`, `GeneratePathParams` | Powers `path`/`pathParams` link props on `BaseButton`/`BaseIconButton`. |
| `@salvon/hooks/useTranslation` → `t` | Translation fn: `t('yes')`, `t('search')`, `t('no_results')`, `t('loading')`. |
| `@salvon/hooks/useTheme` → `useTheme`, `usePalette`, `useThemeMode`, `useIsDarkMode`, `useBreakpoints` | Theme access. |
| `@salvon/components/popover/ConfirmPopover`, `@salvon/components/.../Link` | Composed into buttons via `confirm`/`path` props. |

`Div`/`Flex` usage:
```tsx
<Flex center gap={1} fw>
  <Div p={1.5}>{content}</Div>
</Flex>
```

---

## 10. New-component checklist

1. **Structure**: kebab folder; `index.ts` barrel (named exports + separate `export type` block); `PascalCase.tsx` one component per file; types in `types.d.ts` / `types/Name.d.ts`; hooks co-located as `useXxx.ts`.
2. **Declaration**: `export default function PascalCase`; no `forwardRef`/`displayName`; refs as `ref?: XxxRef` prop + `useImperativeHandle`; `styled()` as `const` default export; variant/preset dispatch via `createElement`.
3. **Types**: `export type XxxProps` alias; extend MUI via `Omit<MUIProps,…> & {…}` with `MUI`-prefixed imports; generic `T extends string | number` for value components; discriminate `multiple` with a union; `never`-exclusion for conflicting props; `import type`.
4. **Imports**: grouped MUI → local types → react → `@salvon/*` last; cross-component via barrel only.
5. **Customization**: expose **`slotProps`** keyed by named slots (`SlotItem<MuiProps>`); spread slot then merge `sx` last; forward extra MUI props via rest spread with defaults **before** `{...props}`.
6. **Data**: take a config **array** (`options`/`items`/`data`) of `{ value/id/name/key, label, disabled?, render?/actions? }`; render children internally, no required compound children.
7. **State**: optional-controlled `value ?? internalValue` (+ `defaultValue`), or `ControllableOptionalState` for overlays, plus a `useImperativeHandle` ref. `onChange` passes enriched context and may return a replacement; provide `onChange`/`onChangeOverride` tiers for RHF controls.
8. **Defaults**: in destructuring; derive from theme (`usePalette().salvon.*`) / other props where sensible; booleans default `false`.
9. **Styling**: `sx` with base → theme token → caller `sx` last; use `voc`; reach for `styled()` only for pseudo/deep-slot/prop-driven CSS (with `shouldForwardProp` for custom props); use `Div`/`Flex`, not `Box`; support both dark-mode idioms; animations via `.salvon-animate*` classes.
10. **A11y & states**: delegate to MUI; add `role`/`tabIndex`/key handlers if you make a bare `div` clickable; provide `loading`/`error`/`helperText`/`disabled` routed through MUI; `t('no_results')` for empty. Variants via string enum + `createElement`, never polymorphic `as`.
