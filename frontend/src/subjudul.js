import { createContext, useContext, useEffect } from 'react';

export const SubJudulContext = createContext(() => {});

export function useSubJudul(teks) {
  const atur = useContext(SubJudulContext);
  useEffect(() => {
    atur(teks || '');
    return () => atur('');
  }, [teks, atur]);
}
