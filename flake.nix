{
  description = "Python dev environment using uv";

  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs { inherit system; };
        
        # Choose your Python version here
        python = pkgs.python312;
        
        # Common C libraries that Python wheels often require.
        # If a specific library fails to load (e.g. "libfoo.so not found"),
        # add it to this list.
        libraryPath = pkgs.lib.makeLibraryPath [
          pkgs.stdenv.cc.cc.lib # libstdc++
          pkgs.zlib
          pkgs.glib
          pkgs.xorg.libX11
        ];

      in
      {
        devShells.default = pkgs.mkShell {
          packages = [
            python
            pkgs.uv
            pkgs.bump2version
          ];

          # 1. Tell uv not to download managed Python versions
          # 2. Tell uv exactly which python interpreter to use (the Nix one)
          env = {
            UV_PYTHON_DOWNLOADS = "never";
            UV_PYTHON = "${python}/bin/python";
          };

          # This effectively fixes the "NixOS problem" with pre-compiled wheels
          # by exposing dynamic libraries they expect to find.
          shellHook = ''
            export LD_LIBRARY_PATH=${libraryPath}:$LD_LIBRARY_PATH
            
            # Create .venv if it doesn't exist
            if [ ! -d ".venv" ]; then
              echo "Creating virtual environment..."
              ${pkgs.uv}/bin/uv venv
            fi
            
            # Activate .venv automatically
            source .venv/bin/activate

            # Install dependencies and the project in editable mode if pyproject.toml has changed
            if [ "pyproject.toml" -nt ".venv_timestamp" ]; then
              echo "pyproject.toml has changed, installing dependencies..."
              ${pkgs.uv}/bin/uv sync
              ${pkgs.uv}/bin/uv pip install -e .
              touch .venv_timestamp
            fi
            
            echo "Python environment ready."
            echo "Python: $(which python)"
            echo "UV: $(which uv)"
          '';
        };
      }
    );
}
