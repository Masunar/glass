import { useEffect, useRef, useState } from 'react';

export type InlineEditSaveResult = boolean | void;

type SaveHandler<V> = (
  value: V,
) => InlineEditSaveResult | Promise<InlineEditSaveResult>;

export function useInlineEdit<V>(
  external: V,
  onSave?: SaveHandler<V>,
  onError?: (error: unknown) => void,
) {
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState<V>(external);
  const [loading, setLoading] = useState(false);
  const savingRef = useRef(false);

  useEffect(() => {
    if (!editing) setValue(external);
  }, [external, editing]);

  const begin = () => {
    setValue(external);
    setEditing(true);
  };

  const cancel = () => {
    setEditing(false);
    setValue(external);
  };

  const save = async () => {
    if (savingRef.current) return;
    savingRef.current = true;
    setLoading(true);
    try {
      const result = await onSave?.(value);
      if (result !== false) setEditing(false);
    } catch (error) {
      onError?.(error);
    } finally {
      setLoading(false);
      savingRef.current = false;
    }
  };

  return { editing, begin, cancel, save, value, setValue, loading };
}
